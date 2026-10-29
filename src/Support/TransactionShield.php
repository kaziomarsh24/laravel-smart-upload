<?php

namespace KaziOmar\SmartUpload\Support;

use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * Class TransactionShield
 *
 * Prevents orphaned "ghost files" on storage disks if a database transaction
 * fails or rolls back, with nested transaction safety and Octane/Queue worker compatibility.
 */
class TransactionShield
{
    /** @var array<string, array{disk: string, path: string}> */
    protected static array $guardedFiles = [];
    protected static bool $listenerRegistered = false;

    /**
     * Protect a newly written file by registering an auto-delete cleanup
     * if the currently active database transaction rolls back.
     * Supports multiple database connections (e.g. multi-tenant / custom connections).
     */
    public static function guard(string $disk, string $path): void
    {
        if (!config('smart-upload.transaction_safe', true)) {
            return;
        }

        $activeConnections = static::getActiveTransactionConnections();

        // Only register if inside an active DB transaction on at least one connection
        if (empty($activeConnections)) {
            return;
        }

        $key = "{$disk}:{$path}";
        static::$guardedFiles[$key] = ['disk' => $disk, 'path' => $path];

        // Disarm file cleanup when active connection transaction successfully commits
        foreach ($activeConnections as $connection) {
            $connection->afterCommit(function () use ($key) {
                unset(static::$guardedFiles[$key]);
            });
        }

        static::ensureListenerRegistered();
    }

    /**
     * Retrieve all active database connections that have an open transaction.
     *
     * @return array<\Illuminate\Database\ConnectionInterface>
     */
    protected static function getActiveTransactionConnections(): array
    {
        $active = [];

        try {
            foreach (DB::getConnections() as $connection) {
                if ($connection->transactionLevel() > 0) {
                    $active[] = $connection;
                }
            }
        } catch (\Throwable) {
            if (DB::transactionLevel() > 0) {
                $active[] = DB::connection();
            }
        }

        return $active;
    }

    /**
     * Ensure the rollback listener is registered only once to prevent memory leaks in Octane.
     */
    protected static function ensureListenerRegistered(): void
    {
        if (static::$listenerRegistered) {
            return;
        }

        static::$listenerRegistered = true;

        Event::listen(TransactionRolledBack::class, function ($event = null) {
            // Check if the rolled-back connection reached root level 0
            $level = 0;
            if (is_object($event) && isset($event->connection)) {
                $level = $event->connection->transactionLevel();
            } else {
                $level = DB::transactionLevel();
            }

            // When root transaction rolls back to level 0, purge all pending files
            if ($level === 0 && !empty(static::$guardedFiles)) {
                foreach (static::$guardedFiles as $key => $file) {
                    if (Storage::disk($file['disk'])->exists($file['path'])) {
                        Storage::disk($file['disk'])->delete($file['path']);
                    }
                }
                static::$guardedFiles = [];
            }
        });
    }

    /**
     * Reset tracked files and listener state (useful for tests or teardown).
     */
    public static function reset(): void
    {
        static::$guardedFiles = [];
        static::$listenerRegistered = false;
    }
}

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
    /** @var array<string, array{disk: string, path: string, connections: array<string>}> */
    protected static array $guardedFiles = [];
    protected static bool $listenerRegistered = false;

    /**
     * Protect a newly written file by registering an auto-delete cleanup
     * if the currently active database transaction rolls back.
     * Supports multiple database connections with strict connection isolation.
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
        $connectionNames = [];

        foreach ($activeConnections as $connection) {
            $connName = $connection->getName();
            $connectionNames[] = $connName;

            // Disarm file cleanup when this specific connection successfully commits
            $connection->afterCommit(function () use ($key, $connName) {
                if (isset(static::$guardedFiles[$key])) {
                    static::$guardedFiles[$key]['connections'] = array_values(array_diff(
                        static::$guardedFiles[$key]['connections'],
                        [$connName]
                    ));

                    // Only fully disarm when all associated connections have committed
                    if (empty(static::$guardedFiles[$key]['connections'])) {
                        unset(static::$guardedFiles[$key]);
                    }
                }
            });
        }

        static::$guardedFiles[$key] = [
            'disk' => $disk,
            'path' => $path,
            'connections' => $connectionNames,
        ];

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
            $connName = null;
            $level = 0;

            if (is_object($event) && isset($event->connection)) {
                $level = $event->connection->transactionLevel();
                $connName = $event->connection->getName();
            } else {
                $level = DB::transactionLevel();
                $connName = DB::getDefaultConnection();
            }

            // Only purge guarded files associated with this specific connection when it reaches root level 0
            if ($level === 0 && !empty(static::$guardedFiles)) {
                foreach (static::$guardedFiles as $key => $file) {
                    if ($connName === null || in_array($connName, $file['connections'], true)) {
                        if (Storage::disk($file['disk'])->exists($file['path'])) {
                            Storage::disk($file['disk'])->delete($file['path']);
                        }
                        unset(static::$guardedFiles[$key]);
                    }
                }
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

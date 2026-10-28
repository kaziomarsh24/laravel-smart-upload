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
     */
    public static function guard(string $disk, string $path): void
    {
        if (!config('smart-upload.transaction_safe', true)) {
            return;
        }

        // Only register if inside an active DB transaction
        if (DB::transactionLevel() <= 0) {
            return;
        }

        $key = "{$disk}:{$path}";
        static::$guardedFiles[$key] = ['disk' => $disk, 'path' => $path];

        // Disarm file cleanup when root transaction successfully commits
        DB::afterCommit(function () use ($key) {
            unset(static::$guardedFiles[$key]);
        });

        static::ensureListenerRegistered();
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

        Event::listen(TransactionRolledBack::class, function () {
            // When root transaction rolls back to level 0, purge all pending files
            if (DB::transactionLevel() === 0 && !empty(static::$guardedFiles)) {
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
     * Reset tracked files (useful for tests or teardown).
     */
    public static function reset(): void
    {
        static::$guardedFiles = [];
    }
}

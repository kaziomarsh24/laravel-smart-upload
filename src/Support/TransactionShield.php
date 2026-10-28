<?php

namespace KaziOmar\SmartUpload\Support;

use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * Class TransactionShield
 *
 * Prevents orphaned "ghost files" on storage disks if a database transaction
 * fails or rolls back, while remaining safe in Octane and queue worker environments.
 */
class TransactionShield
{
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

        $transactionLevel = DB::transactionLevel();
        $handled = false;

        // Disarm the shield when this transaction commits
        Event::listen(TransactionCommitted::class, function () use (&$handled, $transactionLevel) {
            if (!$handled && DB::transactionLevel() < $transactionLevel) {
                $handled = true;
            }
        });

        // Trigger rollback purge only if this specific transaction rolled back
        Event::listen(TransactionRolledBack::class, function () use (&$handled, $disk, $path, $transactionLevel) {
            if (!$handled && DB::transactionLevel() < $transactionLevel) {
                $handled = true;
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            }
        });
    }
}

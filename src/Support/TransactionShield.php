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
 * fails or rolls back.
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

        // Listen for transaction rollback event
        Event::listen(TransactionRolledBack::class, function () use ($disk, $path, $transactionLevel) {
            // Check if transaction has fallen below the level where file was written
            if (DB::transactionLevel() < $transactionLevel) {
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            }
        });
    }
}

<?php

namespace KaziOmar\SmartUpload\Tests\Feature;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use KaziOmar\SmartUpload\Facades\Upload;
use KaziOmar\SmartUpload\Tests\TestCase;

class TransactionRollbackTest extends TestCase
{
    public function test_purges_file_automatically_if_database_transaction_rolls_back(): void
    {
        $uploadedPath = null;

        try {
            DB::transaction(function () use (&$uploadedPath) {
                $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');
                $result = Upload::file($file)->save('contracts');

                $uploadedPath = $result->path;
                // File should temporarily exist on disk
                $this->assertTrue(Storage::disk('public')->exists($uploadedPath));

                // Force a transaction failure/exception
                throw new Exception('Database constraint failure simulation!');
            });
        } catch (Exception $e) {
            // Expected exception
        }

        // Transaction failed and rolled back -> Ghost file should be deleted automatically!
        $this->assertNotNull($uploadedPath);
        $this->assertFalse(Storage::disk('public')->exists($uploadedPath));
    }

    public function test_retains_file_when_database_transaction_commits(): void
    {
        $uploadedPath = null;

        DB::transaction(function () use (&$uploadedPath) {
            $file = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');
            $result = Upload::file($file)->save('invoices');
            $uploadedPath = $result->path;
        });

        // Transaction committed -> File must remain!
        $this->assertNotNull($uploadedPath);
        $this->assertTrue(Storage::disk('public')->exists($uploadedPath));
    }
}

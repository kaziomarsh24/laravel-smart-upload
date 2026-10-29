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

    public function test_purges_file_automatically_if_custom_connection_transaction_rolls_back(): void
    {
        // Setup a secondary/tenant connection pointing to an in-memory database
        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $uploadedPath = null;

        try {
            DB::connection('tenant')->transaction(function () use (&$uploadedPath) {
                $file = UploadedFile::fake()->create('tenant_policy.pdf', 100, 'application/pdf');
                $result = Upload::file($file)->save('tenant_policies');

                $uploadedPath = $result->path;
                $this->assertTrue(Storage::disk('public')->exists($uploadedPath));

                throw new Exception('Simulated tenant transaction rollback!');
            });
        } catch (Exception $e) {
            // Expected
        }

        // Must be purged because tenant connection transaction rolled back
        $this->assertNotNull($uploadedPath);
        $this->assertFalse(Storage::disk('public')->exists($uploadedPath));
    }

    public function test_retains_file_when_custom_connection_transaction_commits(): void
    {
        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $uploadedPath = null;

        DB::connection('tenant')->transaction(function () use (&$uploadedPath) {
            $file = UploadedFile::fake()->create('tenant_lease.pdf', 100, 'application/pdf');
            $result = Upload::file($file)->save('tenant_leases');
            $uploadedPath = $result->path;
        });

        $this->assertNotNull($uploadedPath);
        $this->assertTrue(Storage::disk('public')->exists($uploadedPath));
    }

    public function test_isolates_guarded_files_between_different_database_connections(): void
    {
        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $defaultPath = null;
        $tenantPath = null;

        DB::transaction(function () use (&$defaultPath, &$tenantPath) {
            // 1. Upload file inside default connection transaction
            $file1 = UploadedFile::fake()->create('default_doc.pdf', 100, 'application/pdf');
            $res1 = Upload::file($file1)->save('default_docs');
            $defaultPath = $res1->path;

            // 2. Secondary connection transaction that fails and rolls back
            try {
                DB::connection('tenant')->transaction(function () use (&$tenantPath) {
                    $file2 = UploadedFile::fake()->create('tenant_failed.pdf', 100, 'application/pdf');
                    $res2 = Upload::file($file2)->save('tenant_docs');
                    $tenantPath = $res2->path;

                    throw new Exception('Tenant transaction failure!');
                });
            } catch (Exception $e) {
                // Caught tenant failure
            }

            // At this point:
            // Tenant transaction rolled back -> tenant file MUST be purged!
            $this->assertFalse(Storage::disk('public')->exists($tenantPath));

            // Default connection is STILL active -> default file MUST NOT be purged!
            $this->assertTrue(Storage::disk('public')->exists($defaultPath));
        });

        // Default transaction successfully committed -> default file MUST be retained!
        $this->assertTrue(Storage::disk('public')->exists($defaultPath));
        $this->assertFalse(Storage::disk('public')->exists($tenantPath));
    }
}

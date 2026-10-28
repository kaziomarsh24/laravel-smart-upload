<?php

namespace KaziOmar\SmartUpload\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use KaziOmar\SmartUpload\Tests\TestCase;

class PruneCommandTest extends TestCase
{
    public function test_prunes_expired_temporary_files_via_artisan_command(): void
    {
        $tempDir = 'smart-upload/temp';
        $disk = 'local';

        // Expired file (created 30 hours ago)
        $oldFile = "{$tempDir}/old-abandoned.jpg";
        Storage::disk($disk)->put($oldFile, 'old-temp-data');

        // Touch timestamp to 30 hours in the past
        $fullPath = Storage::disk($disk)->path($oldFile);
        if (file_exists($fullPath)) {
            touch($fullPath, Carbon::now()->subHours(30)->getTimestamp());
        }

        // Fresh file (created 1 hour ago)
        $freshFile = "{$tempDir}/fresh-upload.jpg";
        Storage::disk($disk)->put($freshFile, 'fresh-temp-data');

        // Run artisan prune
        $this->artisan('smart-upload:prune', ['--hours' => 24])
            ->expectsOutputToContain('Pruning complete')
            ->assertSuccessful();

        $this->assertFalse(Storage::disk($disk)->exists($oldFile));
        $this->assertTrue(Storage::disk($disk)->exists($freshFile));
    }
}

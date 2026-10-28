<?php

namespace KaziOmar\SmartUpload\Commands;

use Illuminate\Console\Command;
use KaziOmar\SmartUpload\Chunk\ChunkManager;
use KaziOmar\SmartUpload\Support\TempUploadManager;

/**
 * Class PruneUploadsCommand
 *
 * Artisan command to prune expired temporary files and abandoned chunk directories.
 */
class PruneUploadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smart-upload:prune {--hours= : Hours threshold before deleting expired files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune abandoned chunks and expired temporary uploads from storage.';

    /**
     * Execute the console command.
     */
    public function handle(TempUploadManager $tempManager, ChunkManager $chunkManager): int
    {
        $hours = $this->option('hours') !== null
            ? (int) $this->option('hours')
            : (int) config('smart-upload.temporary.expiration_hours', 24);

        $disk = config('smart-upload.temporary.disk', 'local');
        $tempDir = config('smart-upload.temporary.directory', 'smart-upload/temp');

        $this->info("Pruning files older than {$hours} hours from [{$disk}] disk...");

        // Prune temporary files
        $prunedTemp = $tempManager->prune($disk, $tempDir, $hours);

        // Prune abandoned chunk parts
        $prunedChunks = $chunkManager->prune($hours);

        $total = $prunedTemp + $prunedChunks;
        $this->info("Pruning complete. Removed {$prunedTemp} temp files and {$prunedChunks} abandoned chunk directories ({$total} total).");

        return self::SUCCESS;
    }
}

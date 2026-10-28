<?php

namespace KaziOmar\SmartUpload\Chunk;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use KaziOmar\SmartUpload\Contracts\ChunkHandlerInterface;
use KaziOmar\SmartUpload\DTO\UploadResult;
use KaziOmar\SmartUpload\Exceptions\ChunkException;
use KaziOmar\SmartUpload\Security\SecuritySanitizer;
use KaziOmar\SmartUpload\Support\TransactionShield;

/**
 * Class ChunkManager
 *
 * Handles client-agnostic chunk uploads, tracks progress, and merges chunks
 * into a single unified file using low-memory file streams.
 */
class ChunkManager implements ChunkHandlerInterface
{
    protected string $disk;
    protected string $chunkDir;
    protected string $identifier = '';
    protected int $chunkIndex = 0;
    protected int $totalChunks = 1;
    protected string $originalFilename = '';
    protected bool $finished = false;
    protected float $percentage = 0.0;
    protected SecuritySanitizer $sanitizer;

    public function __construct(
        ?string $disk = null,
        ?string $chunkDir = null,
        ?SecuritySanitizer $sanitizer = null
    ) {
        $this->disk = $disk ?: config('smart-upload.temporary.disk', 'local');
        $this->chunkDir = $chunkDir ?: config('smart-upload.temporary.chunk_directory', 'smart-upload/chunks');
        $this->sanitizer = $sanitizer ?: new SecuritySanitizer();
    }

    /**
     * Parse incoming chunk data from Request.
     */
    public function receive(Request $request): static
    {
        $file = $request->file('file') ?: $request->file('chunk') ?: $request->file('data');

        if (!$file instanceof UploadedFile) {
            throw new ChunkException('No chunk file found in request under file/chunk/data.');
        }

        // Auto-detect identifier from popular chunk upload libraries
        $this->identifier = (string) ($request->input('dzuuid')
            ?: $request->input('uuid')
            ?: $request->input('resumableIdentifier')
            ?: $request->input('qquuid')
            ?: $request->input('upload_id')
            ?: Str::slug($file->getClientOriginalName()));

        $this->chunkIndex = (int) ($request->input('dzchunkindex')
            ?? $request->input('chunk')
            ?? ($request->input('resumableChunkNumber') ? $request->input('resumableChunkNumber') - 1 : 0));

        $this->totalChunks = (int) ($request->input('dztotalchunkcount')
            ?: $request->input('total_chunks')
            ?: $request->input('resumableTotalChunks')
            ?: 1);

        $this->originalFilename = (string) ($request->input('dzfilename')
            ?: $request->input('filename')
            ?: $file->getClientOriginalName());

        // Store this chunk part
        $this->storeChunkPart($file);

        return $this;
    }

    /**
     * Store an individual chunk part file.
     */
    protected function storeChunkPart(UploadedFile $file): void
    {
        $storage = Storage::disk($this->disk);
        $chunkPath = "{$this->chunkDir}/{$this->identifier}/part_{$this->chunkIndex}";

        $stream = fopen($file->getPathname(), 'r');
        $storage->put($chunkPath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $savedParts = count($storage->files("{$this->chunkDir}/{$this->identifier}"));
        $this->percentage = round(($savedParts / max(1, $this->totalChunks)) * 100, 2);
        $this->finished = ($savedParts >= $this->totalChunks);
    }

    public function isComplete(): bool
    {
        return $this->finished;
    }

    public function percentage(): float
    {
        return $this->percentage;
    }

    /**
     * Merge all chunk parts into the final file and store in target directory.
     */
    public function save(string $directory = '', ?string $targetDisk = null): UploadResult
    {
        if (!$this->isComplete()) {
            throw new ChunkException("Cannot save: Upload is only {$this->percentage}% complete.");
        }

        $finalDisk = $targetDisk ?: config('smart-upload.disk', 'public');
        $storage = Storage::disk($this->disk);
        $tempMergedPath = tempnam(sys_get_temp_dir(), 'merged_chunk_');
        $mergedHandle = fopen($tempMergedPath, 'w+');

        // Sequentially stream-write each chunk part
        for ($i = 0; $i < $this->totalChunks; $i++) {
            $partPath = "{$this->chunkDir}/{$this->identifier}/part_{$i}";
            if (!$storage->exists($partPath)) {
                fclose($mergedHandle);
                @unlink($tempMergedPath);
                throw new ChunkException("Missing chunk part {$i} for upload {$this->identifier}.");
            }

            $partStream = $storage->readStream($partPath);
            if ($partStream) {
                stream_copy_to_stream($partStream, $mergedHandle);
                fclose($partStream);
            }
        }

        fclose($mergedHandle);

        try {
            // Security check on merged file
            $extension = pathinfo($this->originalFilename, PATHINFO_EXTENSION);
            $mime = mime_content_type($tempMergedPath) ?: 'application/octet-stream';
            $uploaded = new UploadedFile($tempMergedPath, $this->originalFilename, $mime, null, true);
            $this->sanitizer->sanitize($uploaded);

            // Save to target storage
            $safeName = $this->sanitizer->generateUniqueName($this->originalFilename, $extension);
            $finalPath = trim($directory, '/') ? trim($directory, '/') . '/' . $safeName : $safeName;

            $targetStorage = Storage::disk($finalDisk);
            $readStream = fopen($tempMergedPath, 'r');
            $targetStorage->put($finalPath, $readStream);
            if (is_resource($readStream)) {
                fclose($readStream);
            }

            $size = filesize($tempMergedPath);

            // Clean up temporary chunk parts
            $storage->deleteDirectory("{$this->chunkDir}/{$this->identifier}");

            // Guard against database transaction rollbacks
            TransactionShield::guard($finalDisk, $finalPath);

            return new UploadResult(
                path: $finalPath,
                url: $targetStorage->url($finalPath),
                filename: $safeName,
                originalName: $this->originalFilename,
                extension: $extension,
                mimeType: $mime,
                size: $size ?: 0,
                disk: $finalDisk,
                isTemporary: false
            );
        } finally {
            @unlink($tempMergedPath);
        }
    }

    /**
     * Prune abandoned chunk directories older than the specified hours.
     */
    public function prune(int $expirationHours): int
    {
        $storage = Storage::disk($this->disk);
        if (!$storage->exists($this->chunkDir)) {
            return 0;
        }

        $threshold = Carbon::now()->subHours($expirationHours)->getTimestamp();
        $deleted = 0;

        foreach ($storage->directories($this->chunkDir) as $dir) {
            $files = $storage->files($dir);
            if (empty($files)) {
                $storage->deleteDirectory($dir);
                $deleted++;
                continue;
            }

            $latest = 0;
            foreach ($files as $file) {
                $latest = max($latest, $storage->lastModified($file));
            }

            if ($latest < $threshold) {
                $storage->deleteDirectory($dir);
                $deleted++;
            }
        }

        return $deleted;
    }
}

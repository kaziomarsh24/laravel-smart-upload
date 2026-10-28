<?php

namespace KaziOmar\SmartUpload\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use KaziOmar\SmartUpload\DTO\UploadResult;
use KaziOmar\SmartUpload\Exceptions\UploadException;

/**
 * Class TempUploadManager
 *
 * Manages temporary storage for two-step uploads (e.g., Livewire, React/Flutter dropzones)
 * and handles automatic pruning of expired temporary files.
 */
class TempUploadManager
{
    /**
     * Store a file temporarily.
     */
    public function storeTemp(UploadedFile $file, string $disk, string $tempDirectory): UploadResult
    {
        $extension = $file->getClientOriginalExtension();
        $uuid = Str::uuid()->toString();
        $filename = "{$uuid}.{$extension}";
        $path = trim($tempDirectory, '/') . '/' . $filename;

        $stream = fopen($file->getPathname(), 'r');
        Storage::disk($disk)->put($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $url = Storage::disk($disk)->url($path);

        return new UploadResult(
            path: $path,
            url: $url,
            filename: $filename,
            originalName: $file->getClientOriginalName(),
            extension: $extension,
            mimeType: $file->getMimeType() ?: 'application/octet-stream',
            size: $file->getSize() ?: 0,
            disk: $disk,
            isTemporary: true
        );
    }

    /**
     * Resolve a temporary file path or UUID into an UploadedFile instance.
     */
    public function resolveTempFile(string $tempPathOrUuid, string $disk): UploadedFile
    {
        $storage = Storage::disk($disk);
        $path = $tempPathOrUuid;

        if (!$storage->exists($path)) {
            // Check if user passed just a UUID
            $tempDir = config('smart-upload.temporary.directory', 'smart-upload/temp');
            $matchingFiles = $storage->files($tempDir);

            foreach ($matchingFiles as $file) {
                if (str_contains($file, $tempPathOrUuid)) {
                    $path = $file;
                    break;
                }
            }
        }

        if (!$storage->exists($path)) {
            throw new UploadException("Temporary file not found: {$tempPathOrUuid}");
        }

        $fullPath = $storage->path($path);
        $originalName = basename($path);
        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';

        return new UploadedFile($fullPath, $originalName, $mime, null, true);
    }

    /**
     * Delete expired files from the temporary directory.
     *
     * @return int Number of deleted files
     */
    public function prune(string $disk, string $directory, int $expirationHours): int
    {
        $storage = Storage::disk($disk);

        if (!$storage->exists($directory)) {
            return 0;
        }

        $threshold = Carbon::now()->subHours($expirationHours)->getTimestamp();
        $deletedCount = 0;

        foreach ($storage->files($directory) as $file) {
            $lastModified = $storage->lastModified($file);

            if ($lastModified < $threshold) {
                $storage->delete($file);
                $deletedCount++;
            }
        }

        return $deletedCount;
    }
}

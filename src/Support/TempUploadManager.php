<?php

namespace KaziOmar\SmartUpload\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use KaziOmar\SmartUpload\DTO\UploadResult;
use KaziOmar\SmartUpload\Exceptions\UploadException;
use Throwable;

/**
 * Class TempUploadManager
 *
 * Manages temporary storage for two-step uploads (e.g., Livewire, React/Flutter dropzones)
 * and handles automatic pruning of expired temporary files across both local and remote disks.
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
        if ($stream !== false) {
            Storage::disk($disk)->put($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } else {
            Storage::disk($disk)->put($path, file_get_contents($file->getPathname()) ?: '');
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
     * Resolve a temporary file path or UUID into an UploadedFile instance,
     * safely streaming remote files to a local temp buffer if needed.
     */
    public function resolveTempFile(string $tempPathOrUuid, string $disk): UploadedFile
    {
        $storage = Storage::disk($disk);
        $path = $tempPathOrUuid;

        if (!$storage->exists($path)) {
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

        $fullPath = null;
        try {
            $fullPath = $storage->path($path);
        } catch (Throwable) {
            $fullPath = null;
        }

        // If storage driver is remote (S3/R2/Spaces), create a local copy for processing
        if (!$fullPath || !file_exists($fullPath)) {
            $tempLocal = tempnam(sys_get_temp_dir(), 'smart_temp_');
            file_put_contents($tempLocal, $storage->get($path));
            $fullPath = $tempLocal;
        }

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

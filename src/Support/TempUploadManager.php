<?php

namespace KaziOmar\SmartUpload\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use KaziOmar\SmartUpload\DTO\UploadResult;
use KaziOmar\SmartUpload\Exceptions\SecurityException;
use KaziOmar\SmartUpload\Exceptions\UploadException;
use KaziOmar\SmartUpload\Security\SecuritySanitizer;
use Throwable;

/**
 * Class TempUploadManager
 *
 * Manages temporary storage for two-step uploads with strict UUID validation,
 * path boundary isolation, and automatic pruning.
 */
class TempUploadManager
{
    public function __construct(
        protected ?SecuritySanitizer $sanitizer = null
    ) {
        $this->sanitizer = $sanitizer ?: new SecuritySanitizer();
    }

    /**
     * Store a file temporarily with security validation.
     */
    public function storeTemp(UploadedFile $file, string $disk, string $tempDirectory): UploadResult
    {
        // Enforce strict security sanitization on temporary uploads
        $this->sanitizer->sanitize($file);

        $extension = strtolower($file->getClientOriginalExtension());
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
     * strictly ensuring the target resides inside the designated temp directory.
     */
    public function resolveTempFile(string $tempPathOrUuid, string $disk): UploadedFile
    {
        // Block path traversal or directory escaping
        if (str_contains($tempPathOrUuid, '..') || str_contains($tempPathOrUuid, "\0")) {
            throw new SecurityException("Invalid temporary file identifier: {$tempPathOrUuid}");
        }

        $storage = Storage::disk($disk);
        $tempDir = trim(config('smart-upload.temporary.directory', 'smart-upload/temp'), '/');
        $path = $tempPathOrUuid;

        // Check if user provided only a UUID (with or without extension)
        if (!str_starts_with(trim($path, '/'), $tempDir)) {
            $uuid = pathinfo($tempPathOrUuid, PATHINFO_FILENAME);
            if (preg_match('/^[0-9a-fA-F-]{36}$/', $uuid)) {
                $matches = $storage->files($tempDir);
                $found = false;
                foreach ($matches as $file) {
                    if (basename($file, '.' . pathinfo($file, PATHINFO_EXTENSION)) === $uuid) {
                        $path = $file;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    throw new UploadException("Temporary file with UUID {$uuid} not found.");
                }
            } else {
                throw new SecurityException("Temporary upload path must reside inside [{$tempDir}] directory.");
            }
        }

        // Verify path is within temp directory
        if (!str_starts_with(trim($path, '/'), $tempDir)) {
            throw new SecurityException("Access to path outside temporary directory is forbidden: {$path}");
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

        // For remote cloud disks (S3, R2), copy to local temp buffer for processing
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

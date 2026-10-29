<?php

namespace KaziOmar\SmartUpload\Facades;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Facade;
use KaziOmar\SmartUpload\Chunk\ChunkManager;
use KaziOmar\SmartUpload\DTO\UploadResult;
use KaziOmar\SmartUpload\Security\SecuritySanitizer;
use KaziOmar\SmartUpload\SmartUploader;
use KaziOmar\SmartUpload\Support\TempUploadManager;

/**
 * Class Upload
 *
 * Facade entrypoint for KaziOmar\SmartUpload.
 *
 * @method static SmartUploader file(UploadedFile|string $file)
 * @method static SmartUploader files(array $files)
 * @method static SmartUploader disk(string $disk)
 * @method static SmartUploader preset(string $name)
 * @method static string|null url(?string $pathOrUrl, ?string $defaultDir = null, ?string $disk = null, ?string $fallback = null)
 * @method static bool delete(?string $pathOrUrl, ?string $disk = null)
 *
 * @see \KaziOmar\SmartUpload\SmartUploader
 */
class Upload extends Facade
{
    /**
     * Disable facade caching to prevent builder state leaking across requests
     * or in long-running environments like Laravel Octane.
     */
    protected static $cached = false;

    protected static function getFacadeAccessor(): string
    {
        return 'smart-upload';
    }

    /**
     * Start handling a chunked upload request.
     */
    public static function chunk(Request $request): ChunkManager
    {
        $manager = new ChunkManager();
        return $manager->receive($request);
    }

    /**
     * Immediately sanitize and save a file as temporary and return its UploadResult DTO.
     */
    public static function temp(UploadedFile $file, ?string $disk = null): UploadResult
    {
        $targetDisk = $disk ?: config('smart-upload.temporary.disk', 'local');
        $tempDir = config('smart-upload.temporary.directory', 'smart-upload/temp');

        $manager = new TempUploadManager(new SecuritySanitizer());
        return $manager->storeTemp($file, $targetDisk, $tempDir);
    }

    /**
     * Resume and promote a temporary upload to permanent storage.
     */
    public static function fromTemp(string $tempPathOrUuid, ?string $disk = null): SmartUploader
    {
        $targetDisk = $disk ?: config('smart-upload.temporary.disk', 'local');
        $manager = new TempUploadManager(new SecuritySanitizer());
        $file = $manager->resolveTempFile($tempPathOrUuid, $targetDisk);

        /** @var SmartUploader $uploader */
        $uploader = app('smart-upload');
        $uploader->file($file);
        $uploader->markPromotedFromTemp($tempPathOrUuid, $targetDisk);

        return $uploader;
    }

    /**
     * Format any stored path, plain filename, or external link into a fully qualified URL.
     */
    public static function url(?string $pathOrUrl, ?string $defaultDir = null, ?string $disk = null, ?string $fallback = null): ?string
    {
        /** @var SmartUploader $uploader */
        $uploader = app('smart-upload');
        return $uploader->url($pathOrUrl, $defaultDir, $disk, $fallback);
    }
}

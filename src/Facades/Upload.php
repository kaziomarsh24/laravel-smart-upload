<?php

namespace KaziOmar\SmartUpload\Facades;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Facade;
use KaziOmar\SmartUpload\Chunk\ChunkManager;
use KaziOmar\SmartUpload\DTO\UploadResult;
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
 * @method static bool delete(?string $pathOrUrl, ?string $disk = null)
 *
 * @see \KaziOmar\SmartUpload\SmartUploader
 */
class Upload extends Facade
{
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
     * Immediately save a file as temporary and return its UploadResult DTO.
     */
    public static function temp(UploadedFile $file, ?string $disk = null): UploadResult
    {
        $uploader = static::getFacadeRoot();
        $targetDisk = $disk ?: config('smart-upload.temporary.disk', 'local');
        $tempDir = config('smart-upload.temporary.directory', 'smart-upload/temp');

        $manager = new TempUploadManager();
        return $manager->storeTemp($file, $targetDisk, $tempDir);
    }

    /**
     * Resume and promote a temporary upload to permanent storage.
     */
    public static function fromTemp(string $tempPathOrUuid, ?string $disk = null): SmartUploader
    {
        $targetDisk = $disk ?: config('smart-upload.temporary.disk', 'local');
        $manager = new TempUploadManager();
        $file = $manager->resolveTempFile($tempPathOrUuid, $targetDisk);

        /** @var SmartUploader $uploader */
        $uploader = app('smart-upload');
        return $uploader->file($file);
    }
}

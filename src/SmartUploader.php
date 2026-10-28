<?php

namespace KaziOmar\SmartUpload;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use KaziOmar\SmartUpload\Chunk\ChunkManager;
use KaziOmar\SmartUpload\Contracts\UploaderInterface;
use KaziOmar\SmartUpload\DTO\UploadCollection;
use KaziOmar\SmartUpload\DTO\UploadResult;
use KaziOmar\SmartUpload\Exceptions\UploadException;
use KaziOmar\SmartUpload\Processors\ImageProcessor;
use KaziOmar\SmartUpload\Security\SecuritySanitizer;
use KaziOmar\SmartUpload\Support\TempUploadManager;
use KaziOmar\SmartUpload\Support\TransactionShield;

/**
 * Class SmartUploader
 *
 * Fluent, order-independent file and image upload builder.
 */
class SmartUploader implements UploaderInterface
{
    /** @var array<UploadedFile> */
    protected array $files = [];
    protected ?string $disk = null;
    protected ?bool $convertToWebp = null;
    protected ?int $quality = null;
    protected ?int $scaleDownWidth = null;
    protected ?int $scaleDownHeight = null;
    protected ?array $fitDimensions = null;
    protected ?string $replaceTarget = null;
    protected bool $isTemporary = false;

    public function __construct(
        protected ImageProcessor $imageProcessor = new ImageProcessor(),
        protected SecuritySanitizer $sanitizer = new SecuritySanitizer(),
        protected TempUploadManager $tempManager = new TempUploadManager()
    ) {
    }

    public function file(UploadedFile|string $file): static
    {
        if (is_string($file)) {
            $file = new UploadedFile($file, basename($file), null, null, true);
        }

        $this->files = [$file];
        return $this;
    }

    public function files(array $files): static
    {
        $this->files = array_map(function ($file) {
            return is_string($file) ? new UploadedFile($file, basename($file), null, null, true) : $file;
        }, $files);

        return $this;
    }

    public function disk(string $disk): static
    {
        $this->disk = $disk;
        return $this;
    }

    public function toWebp(?int $quality = null): static
    {
        $this->convertToWebp = true;
        if ($quality !== null) {
            $this->quality = $quality;
        }
        return $this;
    }

    public function keepOriginalFormat(): static
    {
        $this->convertToWebp = false;
        return $this;
    }

    public function quality(int $quality): static
    {
        $this->quality = $quality;
        return $this;
    }

    public function scaleDown(?int $width = null, ?int $height = null): static
    {
        $this->scaleDownWidth = $width;
        $this->scaleDownHeight = $height;
        return $this;
    }

    public function fit(int $width, int $height): static
    {
        $this->fitDimensions = ['width' => $width, 'height' => $height];
        return $this;
    }

    public function preset(string $name): static
    {
        $presets = config('smart-upload.presets', []);
        if (!isset($presets[$name])) {
            throw new UploadException("Undefined upload preset: '{$name}'");
        }

        $config = $presets[$name];
        if (isset($config['fit'])) {
            $this->fit($config['fit'][0], $config['fit'][1]);
        }
        if (isset($config['scale_down'])) {
            $this->scaleDown($config['scale_down'][0], $config['scale_down'][1]);
        }
        if (!empty($config['webp'])) {
            $this->toWebp($config['quality'] ?? null);
        }
        return $this;
    }

    public function replace(?string $oldPathOrUrl): static
    {
        $this->replaceTarget = $oldPathOrUrl;
        return $this;
    }

    public function temporary(): static
    {
        $this->isTemporary = true;
        return $this;
    }

    public function save(string $directory = ''): UploadResult|UploadCollection
    {
        if (empty($this->files)) {
            throw new UploadException('No file specified to upload. Call ->file() or ->files() first.');
        }

        $results = array_map(fn (UploadedFile $file) => $this->processAndSave($file, $directory), $this->files);

        // Auto delete old file if replacement requested
        if ($this->replaceTarget !== null) {
            $this->deleteFile($this->replaceTarget, $this->resolveDisk());
        }

        return count($results) === 1 ? $results[0] : new UploadCollection($results);
    }

    protected function processAndSave(UploadedFile $file, string $directory): UploadResult
    {
        $this->sanitizer->sanitize($file);
        $disk = $this->resolveDisk();

        if ($this->isTemporary) {
            $tempDir = config('smart-upload.temporary.directory', 'smart-upload/temp');
            return $this->tempManager->storeTemp($file, $disk, $tempDir);
        }

        $targetDir = trim($directory, '/');
        $isImage = $this->imageProcessor->isImage($file);
        $shouldWebp = $this->convertToWebp ?? config('smart-upload.auto_webp', true);

        if ($isImage) {
            $image = $this->imageProcessor->read($file);

            if ($this->fitDimensions !== null) {
                $image = $this->imageProcessor->fit($image, $this->fitDimensions['width'], $this->fitDimensions['height']);
            } elseif ($this->scaleDownWidth !== null || $this->scaleDownHeight !== null) {
                $image = $this->imageProcessor->scaleDown($image, $this->scaleDownWidth, $this->scaleDownHeight);
            } else {
                $image = $this->imageProcessor->scaleDown($image, config('smart-upload.max_width'), config('smart-upload.max_height'));
            }

            $format = $shouldWebp ? 'webp' : $file->getClientOriginalExtension();
            $quality = $this->quality ?? config('smart-upload.quality', 85);
            $encoded = $this->imageProcessor->encode($image, $format, $quality);

            $filename = $this->sanitizer->generateUniqueName($file, $format);
            $path = $targetDir ? "{$targetDir}/{$filename}" : $filename;

            Storage::disk($disk)->put($path, $encoded);
            $size = strlen($encoded);
            $mime = "image/{$format}";
        } else {
            $extension = $file->getClientOriginalExtension();
            $filename = $this->sanitizer->generateUniqueName($file, $extension);
            $path = $targetDir ? "{$targetDir}/{$filename}" : $filename;

            $stream = fopen($file->getPathname(), 'r');
            Storage::disk($disk)->put($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $size = $file->getSize() ?: 0;
            $mime = $file->getMimeType() ?: 'application/octet-stream';
        }

        TransactionShield::guard($disk, $path);

        return new UploadResult(
            path: $path,
            url: Storage::disk($disk)->url($path),
            filename: $filename,
            originalName: $file->getClientOriginalName(),
            extension: pathinfo($filename, PATHINFO_EXTENSION),
            mimeType: $mime,
            size: $size,
            disk: $disk,
            isTemporary: false
        );
    }

    public function delete(?string $pathOrUrl, ?string $disk = null): bool
    {
        return $this->deleteFile($pathOrUrl, $disk);
    }

    public function deleteFile(?string $pathOrUrl, ?string $disk = null): bool
    {
        if (!$pathOrUrl) {
            return false;
        }

        $targetDisk = $disk ?: $this->resolveDisk();
        $actualPath = $pathOrUrl;

        if (Str::startsWith($pathOrUrl, ['http://', 'https://'])) {
            $parsed = parse_url($pathOrUrl, PHP_URL_PATH);
            $actualPath = str_replace('/storage/', '', $parsed ?: '');
        }

        $actualPath = ltrim($actualPath, '/');
        $storage = Storage::disk($targetDisk);
        return $storage->exists($actualPath) ? $storage->delete($actualPath) : false;
    }

    protected function resolveDisk(): string
    {
        return $this->disk ?: config('smart-upload.disk', config('filesystems.default', 'public'));
    }
}

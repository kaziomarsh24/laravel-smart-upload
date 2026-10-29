<?php

namespace KaziOmar\SmartUpload;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use KaziOmar\SmartUpload\Contracts\UploaderInterface;
use KaziOmar\SmartUpload\DTO\UploadCollection;
use KaziOmar\SmartUpload\DTO\UploadResult;
use KaziOmar\SmartUpload\Exceptions\UploadException;
use KaziOmar\SmartUpload\Processors\ImageProcessor;
use KaziOmar\SmartUpload\Security\SecuritySanitizer;
use KaziOmar\SmartUpload\Support\TempUploadedFile;
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
    protected bool $isMultiple = false;
    protected ?string $disk = null;
    protected ?bool $convertToWebp = null;
    protected ?int $quality = null;
    protected ?int $scaleDownWidth = null;
    protected ?int $scaleDownHeight = null;
    protected ?array $fitDimensions = null;
    protected ?string $replaceTarget = null;
    protected bool $isTemporary = false;
    protected ?string $promotedTempStoragePath = null;
    protected ?string $promotedTempDisk = null;

    public function __construct(
        protected ImageProcessor $imageProcessor = new ImageProcessor(),
        protected SecuritySanitizer $sanitizer = new SecuritySanitizer(),
        protected TempUploadManager $tempManager = new TempUploadManager()
    ) {
    }

    public function file(UploadedFile|string $file): static
    {
        $this->isMultiple = false;

        if (is_string($file)) {
            $file = new UploadedFile($file, basename($file), null, null, true);
        }

        $this->files = [$file];
        return $this;
    }

    public function files(UploadedFile|array|string $files): static
    {
        $this->isMultiple = true;
        $list = is_array($files) ? $files : [$files];

        $this->files = array_values(array_map(function ($file) {
            return is_string($file) ? new UploadedFile($file, basename($file), null, null, true) : $file;
        }, $list));

        return $this;
    }

    /**
     * Automatically capture uploaded file(s) from an incoming HTTP Request.
     */
    public function request(Request $request, string $fieldName): static
    {
        $uploaded = $request->file($fieldName);

        if ($uploaded === null) {
            $this->files = [];
            $nonPlurals = ['address', 'status', 'pass', 'access', 'business', 'process'];
            $isPlural = str_ends_with($fieldName, 's') && !in_array(strtolower($fieldName), $nonPlurals, true);
            $this->isMultiple = $isPlural || str_ends_with($fieldName, '[]') || is_array($request->input($fieldName));
            return $this;
        }

        if (is_array($uploaded)) {
            $this->isMultiple = true;
            $valid = [];
            foreach ($uploaded as $f) {
                if ($f instanceof UploadedFile) {
                    if (!$f->isValid()) {
                        throw new UploadException("Upload failed for item in '{$fieldName}': " . $f->getErrorMessage());
                    }
                    $valid[] = $f;
                }
            }
            $this->files = $valid;
        } else {
            $this->isMultiple = false;
            if ($uploaded instanceof UploadedFile) {
                if (!$uploaded->isValid()) {
                    throw new UploadException("Upload failed for field '{$fieldName}': " . $uploaded->getErrorMessage());
                }
                $this->files = [$uploaded];
            } else {
                $this->files = [];
            }
        }

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

    public function markPromotedFromTemp(string $resolvedPath, string $tempDisk): static
    {
        $this->promotedTempStoragePath = $resolvedPath;
        $this->promotedTempDisk = $tempDisk;
        return $this;
    }

    public function save(string $directory = ''): UploadResult|UploadCollection
    {
        if (empty($this->files)) {
            if ($this->isMultiple) {
                return new UploadCollection([]);
            }

            throw new UploadException('No file specified to upload. Call ->file(), ->files(), or ->request() first.');
        }

        $results = array_map(fn (UploadedFile $file) => $this->processAndSave($file, $directory), $this->files);

        // Delete old replaced file with transaction rollback protection
        if ($this->replaceTarget !== null) {
            $target = $this->replaceTarget;
            $disk = $this->resolveDisk();

            if (DB::transactionLevel() > 0) {
                DB::afterCommit(fn () => $this->deleteFile($target, $disk));
            } else {
                $this->deleteFile($target, $disk);
            }
        }

        // Clean up promoted temporary files and local buffers with transaction rollback safety
        $cleanupTemp = function () {
            foreach ($this->files as $file) {
                if ($file instanceof TempUploadedFile) {
                    if ($this->promotedTempDisk !== null && !empty($file->resolvedStoragePath)) {
                        Storage::disk($this->promotedTempDisk)->delete($file->resolvedStoragePath);
                    }
                    if (!empty($file->localBufferPath) && file_exists($file->localBufferPath)) {
                        @unlink($file->localBufferPath);
                    }
                }
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($cleanupTemp);
        } else {
            $cleanupTemp();
        }

        return $this->isMultiple ? new UploadCollection($results) : $results[0];
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

            $requestedFormat = $shouldWebp ? 'webp' : strtolower($file->getClientOriginalExtension());
            $quality = $this->quality ?? config('smart-upload.quality', 85);

            [$encoded, $actualFormat] = $this->imageProcessor->encodeSafe($image, $requestedFormat, $quality);

            $filename = $this->sanitizer->generateUniqueName($file, $actualFormat);
            $path = $targetDir ? "{$targetDir}/{$filename}" : $filename;

            Storage::disk($disk)->put($path, $encoded);
            $size = strlen($encoded);
            $mime = $this->imageProcessor->getMimeForFormat($actualFormat);
        } else {
            $extension = $file->getClientOriginalExtension();
            $filename = $this->sanitizer->generateUniqueName($file, $extension);
            $path = $targetDir ? "{$targetDir}/{$filename}" : $filename;

            $stream = fopen($file->getPathname(), 'r');
            if ($stream !== false) {
                Storage::disk($disk)->put($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            } else {
                Storage::disk($disk)->put($path, file_get_contents($file->getPathname()) ?: '');
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

    /**
     * Format any stored path, plain filename, or external link into a fully qualified URL.
     */
    public function url(
        ?string $pathOrUrl,
        ?string $defaultDir = null,
        ?string $disk = null,
        ?string $fallback = null
    ): ?string {
        if ($pathOrUrl === null) {
            return $fallback;
        }

        $trimmed = trim($pathOrUrl);
        if ($trimmed === '') {
            return $fallback;
        }

        // Reject unsafe data URIs (only allow data:image/)
        if (Str::startsWith($trimmed, 'data:')) {
            return Str::startsWith($trimmed, 'data:image/') ? $trimmed : $fallback;
        }

        // Return external URLs or protocol-relative links as-is
        if (Str::startsWith($trimmed, ['http://', 'https://', '//'])) {
            return $trimmed;
        }

        $cleanPath = ltrim($trimmed, '/');

        // Prevent directory traversal or null-byte payload injection
        if (str_contains($cleanPath, '..') || str_contains($cleanPath, "\0")) {
            return $fallback;
        }

        // Strip leading storage/ prefix if passed (e.g. storage/avatars/x.webp)
        $cleanPath = (string) preg_replace('#^storage/#', '', $cleanPath);

        // Prepend default directory if the path is just a plain filename
        if ($defaultDir !== null && !str_contains($cleanPath, '/')) {
            $cleanPath = trim($defaultDir, '/') . '/' . $cleanPath;
        }

        $targetDisk = $disk ?: $this->resolveDisk();

        return Storage::disk($targetDisk)->url($cleanPath);
    }

    public function deleteFile(?string $pathOrUrl, ?string $disk = null): bool
    {
        if (!$pathOrUrl) {
            return false;
        }

        $targetDisk = $disk ?: $this->resolveDisk();
        $actualPath = $pathOrUrl;

        if (Str::startsWith($pathOrUrl, ['http://', 'https://'])) {
            $urlHost = parse_url($pathOrUrl, PHP_URL_HOST);
            $diskUrl = Storage::disk($targetDisk)->url('');
            $diskHost = parse_url($diskUrl, PHP_URL_HOST);
            $appHost = parse_url(config('app.url', ''), PHP_URL_HOST);

            // Never delete local files if the URL points to an external/foreign domain
            if ($urlHost && $urlHost !== $diskHost && $urlHost !== $appHost) {
                return false;
            }

            $parsed = parse_url($pathOrUrl, PHP_URL_PATH);
            $decoded = urldecode($parsed ?: '');
            $actualPath = (string) preg_replace('#^/?storage/#', '', $decoded);
        }

        $actualPath = ltrim($actualPath, '/');
        $actualPath = (string) preg_replace('#^storage/#', '', $actualPath);
        $storage = Storage::disk($targetDisk);

        return $storage->exists($actualPath) ? $storage->delete($actualPath) : false;
    }

    protected function resolveDisk(): string
    {
        return $this->disk ?: config('smart-upload.disk', config('filesystems.default', 'public'));
    }
}

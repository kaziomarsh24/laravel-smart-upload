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
    /** @var array<string, array<string, mixed>> */
    protected array $variantDefinitions = [];

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
            $commonPlurals = ['photos', 'images', 'files', 'attachments', 'documents', 'pictures', 'videos', 'items', 'uploads'];
            $isExplicitMultiple = str_ends_with($fieldName, '[]') || in_array(strtolower($fieldName), $commonPlurals, true) || is_array($request->input($fieldName));
            $this->isMultiple = $isExplicitMultiple;
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

    /**
     * Define multi-size responsive image variants to generate (e.g. mobile, tablet, thumb).
     *
     * @param array<string, array<string, mixed>> $variants
     */
    public function variants(array $variants): static
    {
        $this->variantDefinitions = $variants;
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

        // Delete old replaced file and its sibling variants with transaction rollback protection
        if ($this->replaceTarget !== null) {
            $target = $this->replaceTarget;
            $disk = $this->resolveDisk();

            $doReplace = function () use ($target, $disk) {
                $this->deleteFile($target, $disk);

                // Auto-delete sibling variants of the replaced file
                if (is_string($target) && !str_starts_with($target, 'http://') && !str_starts_with($target, 'https://')) {
                    $knownVariants = config('smart-upload.variant_names', ['thumb', 'thumbnail', 'mobile', 'tablet', 'desktop', 'hd', 'small', 'medium', 'large']);
                    foreach ($knownVariants as $vName) {
                        $vPath = $this->variantPath($target, $vName);
                        if ($vPath !== $target) {
                            $this->deleteFile($vPath, $disk);
                        }
                    }
                }
            };

            if (DB::transactionLevel() > 0) {
                DB::afterCommit($doReplace);
            } else {
                $doReplace();
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

            // Process responsive image variants if requested using memory-friendly cascade
            $generatedVariants = [];
            if (!empty($this->variantDefinitions) && is_array($this->variantDefinitions)) {
                $baseNameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

                // Sort variants descending by target dimension to enable memory-friendly cascading downscale
                $sortedVariants = $this->variantDefinitions;
                uasort($sortedVariants, function ($a, $b) {
                    $dimA = $a['scale_down'][0] ?? ($a['scale_down'] ?? ($a['fit'][0] ?? 0));
                    $dimB = $b['scale_down'][0] ?? ($b['scale_down'] ?? ($b['fit'][0] ?? 0));
                    return $dimB <=> $dimA;
                });

                // Keep working image instance in memory and cascade resize
                $workingImage = $this->imageProcessor->read($file);

                foreach ($sortedVariants as $variantKey => $variantOptions) {
                    $cleanKey = Str::slug((string) $variantKey, '_');
                    if ($cleanKey === '') {
                        continue;
                    }

                    $variantFilename = "{$baseNameWithoutExt}_{$cleanKey}.{$actualFormat}";
                    $variantPath = $targetDir ? "{$targetDir}/{$variantFilename}" : $variantFilename;

                    if (isset($variantOptions['fit']) && is_array($variantOptions['fit'])) {
                        // Fit/crop must operate on a clone so it does not mutate workingImage aspect ratio for subsequent variants
                        $variantImage = $this->imageProcessor->fit(clone $workingImage, $variantOptions['fit'][0], $variantOptions['fit'][1] ?? $variantOptions['fit'][0]);
                    } elseif (isset($variantOptions['scale_down'])) {
                        $scaleParams = is_array($variantOptions['scale_down']) ? $variantOptions['scale_down'] : [$variantOptions['scale_down'], null];
                        $workingImage = $this->imageProcessor->scaleDown($workingImage, $scaleParams[0], $scaleParams[1] ?? null);
                        $variantImage = $workingImage;
                    } else {
                        $variantImage = $workingImage;
                    }

                    $variantQuality = $variantOptions['quality'] ?? $quality;
                    [$varEncoded] = $this->imageProcessor->encodeSafe($variantImage, $actualFormat, $variantQuality);

                    Storage::disk($disk)->put($variantPath, $varEncoded);
                    TransactionShield::guard($disk, $variantPath);

                    $generatedVariants[$variantKey] = $variantPath;
                    unset($varEncoded);
                }

                unset($workingImage);
            }
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
            isTemporary: false,
            variants: $generatedVariants ?? []
        );
    }

    /**
     * Delete one or multiple files/URLs from disk safely.
     * Supports single string path, URL, array of paths, associative variant maps, or raw JSON.
     *
     * @param string|array<mixed>|null $pathOrPaths
     */
    public function delete(string|array|null $pathOrPaths, ?string $disk = null): bool
    {
        return $this->deleteFile($pathOrPaths, $disk);
    }

    /**
     * Compute the relative path for a given variant of a stored image.
     * Skips mutation if the target is an external HTTP/HTTPS URL or data URI.
     */
    public function variantPath(string $path, string $variant): string
    {
        $trimmed = trim($path);

        // Do not alter external URLs or data URIs
        if (Str::startsWith($trimmed, ['http://', 'https://', '//', 'data:'])) {
            return $trimmed;
        }

        $cleanVariant = Str::slug($variant, '_');
        if ($cleanVariant === '') {
            return $path;
        }

        $dir = pathinfo($path, PATHINFO_DIRNAME);
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $ext = pathinfo($path, PATHINFO_EXTENSION);

        $variantFilename = "{$filename}_{$cleanVariant}" . ($ext ? ".{$ext}" : '');

        return ($dir && $dir !== '.') ? "{$dir}/{$variantFilename}" : $variantFilename;
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

    /**
     * Delete one or multiple files/URLs from disk safely.
     * Supports single string path, URL, array of paths, associative variant maps, or raw JSON.
     *
     * @param string|array<mixed>|null $pathOrPaths
     */
    public function deleteFile(string|array|null $pathOrPaths, ?string $disk = null): bool
    {
        if (empty($pathOrPaths)) {
            return false;
        }

        // Support raw JSON strings (e.g. '["a.webp","b.webp"]' or '{"thumb":"a.webp"}')
        if (is_string($pathOrPaths) && (str_starts_with($pathOrPaths, '[') || str_starts_with($pathOrPaths, '{'))) {
            $decoded = json_decode($pathOrPaths, true);
            if (is_array($decoded)) {
                $pathOrPaths = $decoded;
            }
        }

        // Handle array of paths or associative dictionary
        if (is_array($pathOrPaths)) {
            $isAssoc = count(array_filter(array_keys($pathOrPaths), 'is_string')) > 0;

            // Associative dictionary: only delete recognized path keys or registered variant keys
            if ($isAssoc) {
                // 1. Direct candidate keys
                $candidate = $pathOrPaths['path'] ?? $pathOrPaths['url'] ?? $pathOrPaths['file'] ?? null;
                if (is_string($candidate)) {
                    return $this->deleteFile($candidate, $disk);
                }

                // 2. Check if keys match known image variant names (e.g. ['thumb' => '...', 'mobile' => '...'])
                $knownVariants = config('smart-upload.variant_names', ['thumb', 'thumbnail', 'mobile', 'tablet', 'desktop', 'hd', 'small', 'medium', 'large']);
                $deletedAny = false;
                $allVariantSuccess = true;

                foreach ($pathOrPaths as $key => $val) {
                    if (in_array((string) $key, $knownVariants, true) && (is_string($val) || is_array($val))) {
                        $deletedAny = true;
                        if (!$this->deleteFile($val, $disk)) {
                            $allVariantSuccess = false;
                        }
                    }
                }

                if ($deletedAny) {
                    return $allVariantSuccess;
                }

                // If arbitrary associative array with arbitrary keys (e.g. ['label' => 'Logo']), do NOT delete strings as paths!
                return false;
            }

            $allDeleted = true;
            foreach ($pathOrPaths as $item) {
                if (is_string($item) || is_array($item)) {
                    if (!$this->deleteFile($item, $disk)) {
                        $allDeleted = false;
                    }
                }
            }

            return $allDeleted;
        }

        $targetDisk = $disk ?: $this->resolveDisk();
        $actualPath = trim((string) $pathOrPaths);

        // Path traversal / null-byte attack guard
        if (str_contains($actualPath, '..') || str_contains($actualPath, "\0")) {
            return false;
        }

        if (Str::startsWith($actualPath, ['http://', 'https://'])) {
            $urlHost = parse_url($actualPath, PHP_URL_HOST);

            try {
                $diskUrl = Storage::disk($targetDisk)->url('');
                $diskHost = parse_url($diskUrl, PHP_URL_HOST);
            } catch (\Throwable) {
                $diskHost = null;
            }

            $appHost = parse_url(config('app.url', ''), PHP_URL_HOST);

            // Never delete local files if the URL points to an external/foreign domain
            if ($urlHost && $urlHost !== $diskHost && $urlHost !== $appHost) {
                return false;
            }

            $parsed = parse_url($actualPath, PHP_URL_PATH);
            $decoded = urldecode($parsed ?: '');
            $actualPath = (string) preg_replace('#^/?storage/#', '', $decoded);
        }

        $actualPath = ltrim($actualPath, '/');
        $actualPath = (string) preg_replace('#^storage/#', '', $actualPath);

        if (str_contains($actualPath, '..') || str_contains($actualPath, "\0") || trim($actualPath) === '') {
            return false;
        }

        try {
            $storage = Storage::disk($targetDisk);
            return $storage->exists($actualPath) ? $storage->delete($actualPath) : false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Download a file from local or cloud storage and return a StreamedResponse.
     *
     * @param string $path
     * @param string|null $filename Custom download filename (defaults to basename of path)
     * @param array<string, mixed> $headers Optional HTTP response headers
     * @param string|null $disk Target filesystem disk
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     * @throws \KaziOmar\SmartUpload\Exceptions\UploadException
     */
    public function download(string $path, ?string $filename = null, array $headers = [], ?string $disk = null): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $targetDisk = $disk ?: $this->resolveDisk();
        $actualPath = trim($path);

        if (str_contains($actualPath, '..') || str_contains($actualPath, "\0")) {
            throw new \KaziOmar\SmartUpload\Exceptions\UploadException("Invalid path or traversal attempt: {$path}");
        }

        $actualPath = ltrim($actualPath, '/');
        $actualPath = (string) preg_replace('#^storage/#', '', $actualPath);

        $storage = Storage::disk($targetDisk);
        if (!$storage->exists($actualPath)) {
            throw new \KaziOmar\SmartUpload\Exceptions\UploadException("File not found on disk [{$targetDisk}]: {$path}");
        }

        return $storage->download($actualPath, $filename, $headers);
    }

    /**
     * Stream an inline response of a private/local file for in-browser preview (e.g. PDF or image).
     *
     * @param string $path
     * @param array<string, mixed> $headers
     * @param string|null $disk
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     * @throws \KaziOmar\SmartUpload\Exceptions\UploadException
     */
    public function response(string $path, array $headers = [], ?string $disk = null): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $targetDisk = $disk ?: $this->resolveDisk();
        $actualPath = trim($path);

        if (str_contains($actualPath, '..') || str_contains($actualPath, "\0")) {
            throw new \KaziOmar\SmartUpload\Exceptions\UploadException("Invalid path or traversal attempt: {$path}");
        }

        $actualPath = ltrim($actualPath, '/');
        $actualPath = (string) preg_replace('#^storage/#', '', $actualPath);

        $storage = Storage::disk($targetDisk);
        if (!$storage->exists($actualPath)) {
            throw new \KaziOmar\SmartUpload\Exceptions\UploadException("File not found on disk [{$targetDisk}]: {$path}");
        }

        // Apply secure browser headers by default for inline previews (avoiding sandbox on PDF to allow browser PDF viewers)
        $isPdf = str_ends_with(strtolower($actualPath), '.pdf');
        $defaultHeaders = [
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (!$isPdf) {
            $defaultHeaders['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
        }

        return $storage->response($actualPath, headers: array_merge($defaultHeaders, $headers));
    }

    /**
     * Generate a temporary signed URL for a private cloud storage file (e.g. AWS S3, Cloudflare R2).
     *
     * @param string $path
     * @param \DateTimeInterface|int $expiration Expiration time or minutes integer
     * @param array<string, mixed> $options
     * @param string|null $disk
     * @return string
     * @throws \KaziOmar\SmartUpload\Exceptions\UploadException
     */
    public function temporaryUrl(string $path, \DateTimeInterface|int $expiration = 15, array $options = [], ?string $disk = null): string
    {
        $targetDisk = $disk ?: $this->resolveDisk();
        $actualPath = trim($path);

        if (str_contains($actualPath, '..') || str_contains($actualPath, "\0")) {
            throw new \KaziOmar\SmartUpload\Exceptions\UploadException("Invalid path or traversal attempt: {$path}");
        }

        $actualPath = ltrim($actualPath, '/');
        $actualPath = (string) preg_replace('#^storage/#', '', $actualPath);

        $storage = Storage::disk($targetDisk);

        $expirationTime = is_int($expiration) ? now()->addMinutes($expiration) : $expiration;

        try {
            return $storage->temporaryUrl($actualPath, $expirationTime, $options);
        } catch (\Throwable $e) {
            throw new \KaziOmar\SmartUpload\Exceptions\UploadException("Temporary URL generation failed for disk [{$targetDisk}]: " . $e->getMessage(), previous: $e);
        }
    }

    protected function resolveDisk(): string
    {
        return $this->disk ?: config('smart-upload.disk', config('filesystems.default', 'public'));
    }
}

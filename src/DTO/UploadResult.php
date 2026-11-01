<?php

namespace KaziOmar\SmartUpload\DTO;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use JsonSerializable;

/**
 * Class UploadResult
 *
 * Immutable Data Transfer Object representing an uploaded file.
 */
class UploadResult implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * @param array<string, string> $variants Key-value map of variant names to paths
     */
    public function __construct(
        public readonly string $path,
        public readonly string $url,
        public readonly string $filename,
        public readonly string $originalName,
        public readonly string $extension,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly string $disk,
        public readonly bool $isTemporary = false,
        public readonly array $variants = []
    ) {
    }

    /**
     * Get a human-readable file size (e.g., "1.45 MB", "512 KB").
     */
    public function sizeFormatted(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Delete this file and all its generated variants from storage disk.
     */
    public function delete(): bool
    {
        $storage = Storage::disk($this->disk);
        $deleted = true;

        if ($storage->exists($this->path)) {
            $deleted = $storage->delete($this->path);
        }

        foreach ($this->variants as $variantPath) {
            if ($storage->exists($variantPath)) {
                $storage->delete($variantPath);
            }
        }

        return $deleted;
    }

    /**
     * Get fully qualified URLs for all generated variants.
     *
     * @return array<string, string>
     */
    public function variantUrls(): array
    {
        $storage = Storage::disk($this->disk);
        $urls = [];

        foreach ($this->variants as $name => $variantPath) {
            $urls[$name] = $storage->url($variantPath);
        }

        return $urls;
    }

    /**
     * Get the path as an array for polymorphic batch compatibility.
     *
     * @return array<string>
     */
    public function paths(): array
    {
        return [$this->path];
    }

    /**
     * Get the URL as an array for polymorphic batch compatibility.
     *
     * @return array<string>
     */
    public function urls(): array
    {
        return [$this->url];
    }

    /**
     * Convert the DTO to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'url' => $this->url,
            'filename' => $this->filename,
            'original_name' => $this->originalName,
            'extension' => $this->extension,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'size_formatted' => $this->sizeFormatted(),
            'disk' => $this->disk,
            'is_temporary' => $this->isTemporary,
            'variants' => $this->variants,
            'variant_urls' => $this->variantUrls(),
        ];
    }

    /**
     * Convert to JSON string.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Serialize to JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Return a standardized JsonResponse for API controllers.
     */
    public function toResponse(int $status = 200): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $this->toArray(),
        ], $status);
    }
}

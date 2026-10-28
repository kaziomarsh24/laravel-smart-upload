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
    public function __construct(
        public readonly string $path,
        public readonly string $url,
        public readonly string $filename,
        public readonly string $originalName,
        public readonly string $extension,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly string $disk,
        public readonly bool $isTemporary = false
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
     * Delete this file from its storage disk.
     */
    public function delete(): bool
    {
        $storage = Storage::disk($this->disk);

        if ($storage->exists($this->path)) {
            return $storage->delete($this->path);
        }

        return false;
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

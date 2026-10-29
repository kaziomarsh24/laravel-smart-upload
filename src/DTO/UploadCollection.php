<?php

namespace KaziOmar\SmartUpload\DTO;

use Illuminate\Support\Collection;

/**
 * Class UploadCollection
 *
 * Collection of UploadResult instances with batch utility methods.
 *
 * @extends Collection<int, UploadResult>
 */
class UploadCollection extends Collection
{
    /**
     * Get an array of all uploaded file paths.
     *
     * @return array<string>
     */
    public function paths(): array
    {
        return $this->map(fn (UploadResult $result) => $result->path)->all();
    }

    /**
     * Get an array of all uploaded file URLs.
     *
     * @return array<string>
     */
    public function urls(): array
    {
        return $this->map(fn (UploadResult $result) => $result->url)->all();
    }

    /**
     * Get the first file's path (convenience shortcut).
     */
    public function path(): ?string
    {
        return $this->first()?->path;
    }

    /**
     * Get the first file's URL (convenience shortcut).
     */
    public function url(): ?string
    {
        return $this->first()?->url;
    }

    /**
     * Delete all uploaded files in this collection from disk.
     */
    public function delete(): void
    {
        $this->each(fn (UploadResult $result) => $result->delete());
    }

    /**
     * Magic property getter for seamless compatibility with single-result callers.
     */
    public function __get($key)
    {
        return match ($key) {
            'path' => $this->path(),
            'url' => $this->url(),
            default => parent::__get($key),
        };
    }
}

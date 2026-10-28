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
     * Delete all uploaded files in this collection from disk.
     */
    public function delete(): void
    {
        $this->each(fn (UploadResult $result) => $result->delete());
    }
}

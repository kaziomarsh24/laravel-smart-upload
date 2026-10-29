<?php

namespace KaziOmar\SmartUpload\Contracts;

use Illuminate\Http\UploadedFile;
use KaziOmar\SmartUpload\DTO\UploadResult;
use KaziOmar\SmartUpload\DTO\UploadCollection;

/**
 * Interface UploaderInterface
 *
 * Defines the contract for the fluent upload builder.
 */
interface UploaderInterface
{
    /**
     * Set the target file to upload.
     */
    public function file(UploadedFile|string $file): static;

    /**
     * Set multiple files or a single file to upload in batch.
     *
     * @param UploadedFile|array<UploadedFile|string>|string $files
     */
    public function files(UploadedFile|array|string $files): static;

    /**
     * Automatically capture and validate uploaded files from an incoming HTTP Request.
     */
    public function request(\Illuminate\Http\Request $request, string $fieldName): static;

    /**
     * Specify the target filesystem disk.
     */
    public function disk(string $disk): static;

    /**
     * Enable or force WebP image conversion.
     */
    public function toWebp(int $quality = 85): static;

    /**
     * Scale down image dimensions preserving aspect ratio.
     */
    public function scaleDown(?int $width = null, ?int $height = null): static;

    /**
     * Crop & fit image to exact dimensions.
     */
    public function fit(int $width, int $height): static;

    /**
     * Replace an existing file on disk upon successful save.
     */
    public function replace(?string $oldPathOrUrl): static;

    /**
     * Mark the upload as temporary.
     */
    public function temporary(): static;

    /**
     * Execute and save the upload to the given directory.
     */
    public function save(string $directory = ''): UploadResult|UploadCollection;
}

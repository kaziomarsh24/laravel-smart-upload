<?php

namespace KaziOmar\SmartUpload\Support;

use Illuminate\Http\UploadedFile;

/**
 * Class TempUploadedFile
 *
 * Specialized UploadedFile carrying metadata about its temporary storage path
 * and local buffer for automatic post-promotion cleanup.
 */
class TempUploadedFile extends UploadedFile
{
    public function __construct(
        string $path,
        string $originalName,
        ?string $mimeType = null,
        ?int $error = null,
        bool $test = false,
        public readonly string $resolvedStoragePath = '',
        public readonly ?string $localBufferPath = null
    ) {
        parent::__construct($path, $originalName, $mimeType, $error, $test);
    }
}

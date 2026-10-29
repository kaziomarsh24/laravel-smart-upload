<?php

use KaziOmar\SmartUpload\Facades\Upload;

if (!function_exists('upload_url')) {
    /**
     * Format any stored file path, external URL, or plain filename into a fully qualified URL.
     *
     * @param string|null $pathOrUrl Stored relative path or external link.
     * @param string|null $defaultDir Optional directory prefix for plain filenames (e.g. 'avatars').
     * @param string|null $disk Storage disk name (defaults to smart-upload config).
     * @param string|null $fallback Default URL/placeholder if the path is null or empty.
     * @return string|null
     */
    function upload_url(?string $pathOrUrl, ?string $defaultDir = null, ?string $disk = null, ?string $fallback = null): ?string
    {
        return Upload::url($pathOrUrl, $defaultDir, $disk, $fallback);
    }
}

<?php

namespace KaziOmar\SmartUpload\Security;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use KaziOmar\SmartUpload\Exceptions\SecurityException;

/**
 * Class SecuritySanitizer
 *
 * Inspects uploaded files for MIME spoofing, forbidden extensions, path traversal,
 * and malicious payloads.
 */
class SecuritySanitizer
{
    /**
     * @param array<string> $disallowedExtensions
     */
    public function __construct(
        protected array $disallowedExtensions = []
    ) {
        if (empty($this->disallowedExtensions)) {
            $this->disallowedExtensions = config('smart-upload.disallowed_extensions', [
                'php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'sh', 'bat', 'cgi',
                'pl', 'py', 'jar', 'jsp', 'asp', 'aspx', 'vbs', 'com'
            ]);
        }
    }

    /**
     * Validate and sanitize an incoming uploaded file.
     *
     * @throws SecurityException
     */
    public function sanitize(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $originalName = $file->getClientOriginalName();

        // Check for null-byte or directory traversal attacks in filename
        if (str_contains($originalName, "\0") || str_contains($originalName, '..') || str_contains($originalName, '/') || str_contains($originalName, '\\')) {
            throw new SecurityException("Potential path traversal or null-byte detected in filename: {$originalName}");
        }

        // Check against forbidden executable extensions
        if (in_array($extension, $this->disallowedExtensions, true)) {
            throw new SecurityException("Files with extension '.{$extension}' are forbidden for security reasons.");
        }

        // Real MIME type verification (Magic Bytes check via Fileinfo)
        $realMime = $this->detectRealMime($file->getPathname());

        $forbiddenMimes = [
            'application/x-php',
            'text/x-php',
            'application/x-httpd-php',
            'application/x-executable',
            'application/x-msdos-program',
            'application/x-sh',
            'text/x-shellscript',
        ];

        if (in_array($realMime, $forbiddenMimes, true)) {
            throw new SecurityException("Malicious file payload detected. True MIME type: {$realMime}");
        }

        // Check for double extension spoofing (e.g., invoice.php.jpg)
        if ($this->hasDisallowedDoubleExtension($originalName)) {
            throw new SecurityException("Dangerous multi-extension detected in filename: {$originalName}");
        }

        // SVG specific XSS inspection
        if ($extension === 'svg' || $realMime === 'image/svg+xml') {
            $this->inspectSvg($file->getPathname());
        }
    }

    /**
     * Detect actual MIME type using PHP fileinfo buffer.
     */
    public function detectRealMime(string $path): string
    {
        if (!file_exists($path)) {
            return 'application/octet-stream';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mime ?: 'application/octet-stream';
    }

    /**
     * Inspect SVG content for malicious script tags or event handlers.
     *
     * @throws SecurityException
     */
    protected function inspectSvg(string $path): void
    {
        $content = file_get_contents($path);

        if ($content === false) {
            return;
        }

        $dangerousPatterns = [
            '/<script[\s\S]*?>[\s\S]*?<\/script>/i',
            '/javascript\s*:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/onclick\s*=/i',
            '/<foreignObject/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new SecurityException('SVG file contains potentially dangerous script content.');
            }
        }
    }

    /**
     * Check if a filename contains a hidden executable extension before the final extension.
     */
    protected function hasDisallowedDoubleExtension(string $filename): bool
    {
        $parts = explode('.', strtolower($filename));
        array_pop($parts); // Remove final extension

        foreach ($parts as $part) {
            if (in_array($part, $this->disallowedExtensions, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a sanitized, unique filename.
     */
    public function generateUniqueName(UploadedFile|string $file, ?string $extension = null): string
    {
        $original = is_string($file) ? basename($file) : $file->getClientOriginalName();
        $ext = $extension ?: (is_string($file) ? pathinfo($file, PATHINFO_EXTENSION) : $file->getClientOriginalExtension());
        $nameOnly = pathinfo($original, PATHINFO_FILENAME);

        $safeSlug = Str::slug($nameOnly);
        $random = Str::random(12);

        return $safeSlug ? "{$safeSlug}_{$random}.{$ext}" : "{$random}.{$ext}";
    }
}

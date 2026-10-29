<?php

namespace KaziOmar\SmartUpload\Security;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use KaziOmar\SmartUpload\Exceptions\SecurityException;

/**
 * Class SecuritySanitizer
 *
 * Inspects uploaded files for MIME spoofing, forbidden extensions, path traversal,
 * SVG XSS attacks, and malicious payloads.
 */
class SecuritySanitizer
{
    /**
     * @param array<string> $disallowedExtensions
     */
    public function __construct(
        protected array $disallowedExtensions = []
    ) {
        $baseline = [
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phar', 'phps',
            'phtml', 'exe', 'sh', 'bat', 'cgi', 'pl', 'py', 'jar', 'jsp', 'asp',
            'aspx', 'vbs', 'com', 'htaccess', 'html', 'htm'
        ];

        if (empty($this->disallowedExtensions)) {
            $configured = config('smart-upload.disallowed_extensions', []);
            $this->disallowedExtensions = array_values(array_unique(array_merge($baseline, $configured)));
        } else {
            $this->disallowedExtensions = array_values(array_unique(array_merge($baseline, $this->disallowedExtensions)));
        }

        // Allow deliberate whitelist override if configured (e.g. developer portals allowing .py/.sh)
        $explicitlyAllowed = config('smart-upload.allowed_extensions', []);
        if (!empty($explicitlyAllowed) && is_array($explicitlyAllowed)) {
            $this->disallowedExtensions = array_values(array_diff($this->disallowedExtensions, $explicitlyAllowed));
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
        if (str_contains($originalName, "\0") || str_contains($originalName, '../') || str_contains($originalName, '..\\') || str_contains($originalName, '/') || str_contains($originalName, '\\')) {
            throw new SecurityException("Potential path traversal or null-byte detected in filename: {$originalName}");
        }

        // Check against forbidden executable extensions
        if (in_array($extension, $this->disallowedExtensions, true)) {
            throw new SecurityException("Files with extension '.{$extension}' are forbidden for security reasons.");
        }

        // Check for dangerous double extension spoofing (e.g., invoice.php.jpg)
        if ($this->hasDisallowedDoubleExtension($originalName)) {
            throw new SecurityException("Dangerous multi-extension detected in filename: {$originalName}");
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

        // Verify that claimed images actually have an image MIME signature
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'avif'];
        if (in_array($extension, $imageExtensions, true) && !str_starts_with($realMime, 'image/')) {
            throw new SecurityException("Claimed image extension '.{$extension}' does not match true MIME type: {$realMime}");
        }

        // SVG specific comprehensive XSS inspection
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
     * Inspect SVG content for malicious script tags, any on* event handlers, or foreign objects.
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
            '/\bon\w+\s*=/i', // Matches all HTML event handlers: onload, onerror, onmouseover, onfocus, etc.
            '/<foreignObject/i',
            '/data:text\/html/i',
            '/<iframe/i',
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

        // Executable script extensions that should never appear in the middle of a filename
        $middleBlacklist = [
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phar', 'phps',
            'phtml', 'exe', 'sh', 'bat', 'cgi', 'pl', 'py', 'jar', 'jsp', 'asp',
            'aspx', 'vbs', 'htaccess'
        ];

        foreach ($parts as $part) {
            if (in_array($part, $middleBlacklist, true)) {
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

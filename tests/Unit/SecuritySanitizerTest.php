<?php

namespace KaziOmar\SmartUpload\Tests\Unit;

use Illuminate\Http\UploadedFile;
use KaziOmar\SmartUpload\Exceptions\SecurityException;
use KaziOmar\SmartUpload\Security\SecuritySanitizer;
use KaziOmar\SmartUpload\Tests\TestCase;

class SecuritySanitizerTest extends TestCase
{
    protected SecuritySanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new SecuritySanitizer();
    }

    public function test_rejects_forbidden_executable_extensions(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage("Files with extension '.php' are forbidden");

        $file = UploadedFile::fake()->create('malicious.php', 100);
        $this->sanitizer->sanitize($file);
    }

    public function test_detects_path_traversal_in_filename(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Potential path traversal or null-byte detected');

        $temp = tempnam(sys_get_temp_dir(), 'sec_');
        $file = new UploadedFile($temp, '..etc..passwd.jpg', 'image/jpeg', null, true);
        
        try {
            $this->sanitizer->sanitize($file);
        } finally {
            @unlink($temp);
        }
    }

    public function test_detects_double_extension_spoofing(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Dangerous multi-extension detected');

        $file = UploadedFile::fake()->create('avatar.php.jpg', 100);
        $this->sanitizer->sanitize($file);
    }

    public function test_detects_malicious_svg_script_tags(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('SVG file contains potentially dangerous script content.');

        $maliciousSvg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert("XSS")</script></svg>';
        $tempPath = tempnam(sys_get_temp_dir(), 'svg_');
        file_put_contents($tempPath, $maliciousSvg);

        $file = new UploadedFile($tempPath, 'bad.svg', 'image/svg+xml', null, true);
        $this->sanitizer->sanitize($file);
    }

    public function test_generates_safe_unique_slugged_filenames(): void
    {
        $file = UploadedFile::fake()->create('My Special Photo 2026!.png', 50);
        $uniqueName = $this->sanitizer->generateUniqueName($file, 'webp');

        $this->assertStringStartsWith('my-special-photo-2026_', $uniqueName);
        $this->assertStringEndsWith('.webp', $uniqueName);
    }
}

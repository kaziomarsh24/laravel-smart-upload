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

    public function test_rejects_phar_and_html_executable_extensions(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage("Files with extension '.phar' are forbidden");

        $file = UploadedFile::fake()->create('archive.phar', 100);
        $this->sanitizer->sanitize($file);
    }

    public function test_detects_path_traversal_in_filename(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Potential path traversal or null-byte detected');

        $temp = tempnam(sys_get_temp_dir(), 'sec_');
        $file = new UploadedFile($temp, "passwd\0.jpg", 'image/jpeg', null, true);
        
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

    public function test_allows_explicitly_configured_whitelisted_extensions(): void
    {
        // Explicitly allow .py in config (e.g. for developer portal)
        config()->set('smart-upload.allowed_extensions', ['py']);

        $sanitizer = new SecuritySanitizer();
        $file = UploadedFile::fake()->create('script.py', 10, 'text/x-python');

        // Should not throw SecurityException for extension
        $sanitizer->sanitize($file);
        $this->assertTrue(true);
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

    public function test_detects_svg_xss_with_arbitrary_event_handlers(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('SVG file contains potentially dangerous script content.');

        $svgWithOnMouseOver = '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="40" onmouseover="alert(1)" /></svg>';
        $tempPath = tempnam(sys_get_temp_dir(), 'svg2_');
        file_put_contents($tempPath, $svgWithOnMouseOver);

        $file = new UploadedFile($tempPath, 'bad_mouse.svg', 'image/svg+xml', null, true);
        try {
            $this->sanitizer->sanitize($file);
        } finally {
            @unlink($tempPath);
        }
    }

    public function test_allows_legitimate_domain_names_with_dots_like_amazon_com_pdf(): void
    {
        $pdf = UploadedFile::fake()->create('amazon.com.invoice.pdf', 50, 'application/pdf');

        // Should not throw SecurityException
        $this->sanitizer->sanitize($pdf);
        $this->assertTrue(true);
    }

    public function test_rejects_php_file_disguised_with_jpg_extension(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Malicious file payload detected. True MIME type');

        $temp = tempnam(sys_get_temp_dir(), 'php_');
        file_put_contents($temp, '<?php echo "evil payload"; ?>');
        $file = new UploadedFile($temp, 'photo.jpg', 'image/jpeg', null, true);

        try {
            $this->sanitizer->sanitize($file);
        } finally {
            @unlink($temp);
        }
    }

    public function test_generates_safe_unique_slugged_filenames(): void
    {
        $file = UploadedFile::fake()->create('My Special Photo 2026!.png', 50);
        $uniqueName = $this->sanitizer->generateUniqueName($file, 'webp');

        $this->assertStringStartsWith('my-special-photo-2026_', $uniqueName);
        $this->assertStringEndsWith('.webp', $uniqueName);
    }
}

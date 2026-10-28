<?php

namespace KaziOmar\SmartUpload\Tests\Unit;

use Illuminate\Http\UploadedFile;
use KaziOmar\SmartUpload\Processors\ImageProcessor;
use KaziOmar\SmartUpload\Tests\TestCase;

class ImageProcessorTest extends TestCase
{
    protected ImageProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new ImageProcessor();
    }

    public function test_correctly_identifies_images(): void
    {
        $imageFile = UploadedFile::fake()->image('photo.jpg');
        $pdfFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->assertTrue($this->processor->isImage($imageFile));
        $this->assertFalse($this->processor->isImage($pdfFile));
    }

    public function test_scales_down_large_images_preserving_ratio(): void
    {
        $realPath = $this->createFakeImage('large.jpg', 1600, 1200);
        $image = $this->processor->read($realPath);

        $scaled = $this->processor->scaleDown($image, 800, 600);

        $this->assertEquals(800, $scaled->width());
        $this->assertEquals(600, $scaled->height());

        @unlink($realPath);
    }

    public function test_fit_crops_to_exact_square_dimensions(): void
    {
        $realPath = $this->createFakeImage('avatar.jpg', 800, 400);
        $image = $this->processor->read($realPath);

        $fitted = $this->processor->fit($image, 200, 200);

        $this->assertEquals(200, $fitted->width());
        $this->assertEquals(200, $fitted->height());

        @unlink($realPath);
    }

    public function test_encodes_to_webp_format(): void
    {
        $realPath = $this->createFakeImage('photo.jpg', 200, 200);
        $image = $this->processor->read($realPath);

        $encoded = $this->processor->encode($image, 'webp', 80);

        // WebP magic header starts with RIFF and has WEBP
        $this->assertStringStartsWith('RIFF', $encoded);
        $this->assertStringContainsString('WEBP', substr($encoded, 0, 16));

        @unlink($realPath);
    }
}

<?php

namespace KaziOmar\SmartUpload\Tests;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use KaziOmar\SmartUpload\Facades\Upload;
use KaziOmar\SmartUpload\SmartUploadServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Storage::fake('s3');
    }

    protected function getPackageProviders($app): array
    {
        return [
            SmartUploadServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Upload' => Upload::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('filesystems.default', 'public');
        $app['config']->set('smart-upload.disk', 'public');
        $app['config']->set('smart-upload.auto_webp', true);
        $app['config']->set('smart-upload.quality', 85);
        $app['config']->set('smart-upload.temporary.disk', 'local');
        $app['config']->set('smart-upload.temporary.directory', 'smart-upload/temp');
        $app['config']->set('smart-upload.temporary.chunk_directory', 'smart-upload/chunks');
    }

    /**
     * Helper to create a fake real image for testing using Intervention.
     */
    protected function createFakeImage(string $filename = 'test.jpg', int $width = 400, int $height = 300): string
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->create($width, $height)->fill('ff0000');
        $path = tempnam(sys_get_temp_dir(), 'test_img_') . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        file_put_contents($path, (string) $image->toJpeg());
        return $path;
    }
}

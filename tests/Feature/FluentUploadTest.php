<?php

namespace KaziOmar\SmartUpload\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use KaziOmar\SmartUpload\DTO\UploadCollection;
use KaziOmar\SmartUpload\DTO\UploadResult;
use KaziOmar\SmartUpload\Facades\Upload;
use KaziOmar\SmartUpload\Tests\TestCase;

class FluentUploadTest extends TestCase
{
    public function test_uploads_single_image_with_automatic_webp_conversion(): void
    {
        $realPath = $this->createFakeImage('avatar.jpg', 600, 400);
        $file = new UploadedFile($realPath, 'avatar.jpg', 'image/jpeg', null, true);

        $result = Upload::file($file)->save('avatars');

        $this->assertInstanceOf(UploadResult::class, $result);
        $this->assertEquals('webp', $result->extension);
        $this->assertStringStartsWith('avatars/avatar_', $result->path);
        $this->assertTrue(Storage::disk('public')->exists($result->path));

        @unlink($realPath);
    }

    public function test_fluent_order_independence(): void
    {
        $path1 = $this->createFakeImage('one.jpg', 400, 400);
        $file1 = new UploadedFile($path1, 'one.jpg', 'image/jpeg', null, true);

        // Order 1: scaleDown then toWebp
        $res1 = Upload::file($file1)->scaleDown(200, 200)->toWebp(80)->save('test1');

        $path2 = $this->createFakeImage('two.jpg', 400, 400);
        $file2 = new UploadedFile($path2, 'two.jpg', 'image/jpeg', null, true);

        // Order 2: toWebp then scaleDown
        $res2 = Upload::file($file2)->toWebp(80)->scaleDown(200, 200)->save('test2');

        $this->assertEquals('webp', $res1->extension);
        $this->assertEquals('webp', $res2->extension);
        $this->assertTrue(Storage::disk('public')->exists($res1->path));
        $this->assertTrue(Storage::disk('public')->exists($res2->path));

        @unlink($path1);
        @unlink($path2);
    }

    public function test_batch_upload_returns_upload_collection(): void
    {
        $path1 = $this->createFakeImage('img1.jpg');
        $path2 = $this->createFakeImage('img2.jpg');

        $file1 = new UploadedFile($path1, 'img1.jpg', 'image/jpeg', null, true);
        $file2 = new UploadedFile($path2, 'img2.jpg', 'image/jpeg', null, true);

        $collection = Upload::files([$file1, $file2])->save('gallery');

        $this->assertInstanceOf(UploadCollection::class, $collection);
        $this->assertCount(2, $collection);
        $this->assertCount(2, $collection->paths());

        @unlink($path1);
        @unlink($path2);
    }

    public function test_handles_non_image_raw_files_cleanly(): void
    {
        $pdf = UploadedFile::fake()->create('report.pdf', 150, 'application/pdf');

        $result = Upload::file($pdf)->save('documents');

        $this->assertInstanceOf(UploadResult::class, $result);
        $this->assertEquals('pdf', $result->extension);
        $this->assertEquals('application/pdf', $result->mimeType);
        $this->assertTrue(Storage::disk('public')->exists($result->path));
    }

    public function test_automatically_deletes_old_file_when_replacement_is_requested(): void
    {
        Storage::disk('public')->put('avatars/old-avatar.webp', 'old-content');
        $this->assertTrue(Storage::disk('public')->exists('avatars/old-avatar.webp'));

        $realPath = $this->createFakeImage('new-avatar.jpg');
        $file = new UploadedFile($realPath, 'new-avatar.jpg', 'image/jpeg', null, true);

        $result = Upload::file($file)
            ->replace('avatars/old-avatar.webp')
            ->save('avatars');

        // New file exists, old file is gone
        $this->assertTrue(Storage::disk('public')->exists($result->path));
        $this->assertFalse(Storage::disk('public')->exists('avatars/old-avatar.webp'));

        @unlink($realPath);
    }

    public function test_named_preset_execution(): void
    {
        $realPath = $this->createFakeImage('photo.jpg', 800, 600);
        $file = new UploadedFile($realPath, 'photo.jpg', 'image/jpeg', null, true);

        $result = Upload::file($file)->preset('avatar')->save('avatars');

        $this->assertInstanceOf(UploadResult::class, $result);
        $this->assertEquals('webp', $result->extension);
        $this->assertTrue(Storage::disk('public')->exists($result->path));

        @unlink($realPath);
    }

    public function test_temporary_upload_and_promotion_to_permanent(): void
    {
        $realPath = $this->createFakeImage('temp-photo.jpg');
        $file = new UploadedFile($realPath, 'temp-photo.jpg', 'image/jpeg', null, true);

        // Step 1: Upload to temporary storage
        $tempResult = Upload::temp($file);
        $this->assertTrue($tempResult->isTemporary);
        $this->assertTrue(Storage::disk('local')->exists($tempResult->path));

        // Step 2: Promote from temp to permanent avatars directory with WebP
        $finalResult = Upload::fromTemp($tempResult->path)->toWebp()->save('avatars');

        $this->assertInstanceOf(UploadResult::class, $finalResult);
        $this->assertEquals('webp', $finalResult->extension);
        $this->assertTrue(Storage::disk('public')->exists($finalResult->path));

        @unlink($realPath);
    }

    public function test_safe_handling_of_null_or_invalid_delete_requests(): void
    {
        $this->assertFalse(Upload::delete(null));
        $this->assertFalse(Upload::delete('non/existent/file.jpg'));
    }

    public function test_routes_upload_to_custom_disk_like_s3(): void
    {
        $pdf = UploadedFile::fake()->create('contract.pdf', 50, 'application/pdf');

        $result = Upload::file($pdf)->disk('s3')->save('contracts');

        $this->assertEquals('s3', $result->disk);
        $this->assertTrue(Storage::disk('s3')->exists($result->path));
        $this->assertFalse(Storage::disk('public')->exists($result->path));
    }

    public function test_retains_original_format_when_explicitly_requested(): void
    {
        $realPath = $this->createFakeImage('original.png');
        $file = new UploadedFile($realPath, 'original.png', 'image/png', null, true);

        $result = Upload::file($file)->keepOriginalFormat()->save('icons');

        $this->assertEquals('png', $result->extension);
        $this->assertTrue(Storage::disk('public')->exists($result->path));

        @unlink($realPath);
    }

    public function test_throws_exception_when_saving_without_files(): void
    {
        $this->expectException(\KaziOmar\SmartUpload\Exceptions\UploadException::class);
        $this->expectExceptionMessage('No file specified to upload');

        (new \KaziOmar\SmartUpload\SmartUploader())->save('avatars');
    }

    public function test_throws_exception_for_undefined_preset(): void
    {
        $this->expectException(\KaziOmar\SmartUpload\Exceptions\UploadException::class);
        $this->expectExceptionMessage("Undefined upload preset: 'non_existent_preset'");

        $file = UploadedFile::fake()->create('test.jpg', 10);
        Upload::file($file)->preset('non_existent_preset');
    }

    public function test_handles_zero_byte_raw_file_upload_cleanly(): void
    {
        $emptyFile = UploadedFile::fake()->create('empty.txt', 0, 'text/plain');

        $result = Upload::file($emptyFile)->save('logs');

        $this->assertInstanceOf(UploadResult::class, $result);
        $this->assertEquals(0, $result->size);
        $this->assertTrue(Storage::disk('public')->exists($result->path));
    }
}

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

    public function test_facade_does_not_leak_builder_state_across_calls(): void
    {
        $processor = new \KaziOmar\SmartUpload\Processors\ImageProcessor();

        $path1 = $this->createFakeImage('first.jpg', 600, 400);
        $file1 = new UploadedFile($path1, 'first.jpg', 'image/jpeg', null, true);

        // Call 1: Sets fit dimensions (square 120x120)
        $result1 = Upload::file($file1)->fit(120, 120)->save('test_state1');
        $img1 = $processor->read(Storage::disk('public')->path($result1->path));
        $this->assertEquals(120, $img1->width());
        $this->assertEquals(120, $img1->height());

        $path2 = $this->createFakeImage('second.jpg', 600, 400);
        $file2 = new UploadedFile($path2, 'second.jpg', 'image/jpeg', null, true);

        // Call 2: Fresh call without fit, should NOT inherit 120x120
        $result2 = Upload::file($file2)->save('test_state2');
        $img2 = $processor->read(Storage::disk('public')->path($result2->path));
        $this->assertNotEquals(120, $img2->width());
        $this->assertEquals(600, $img2->width());
        $this->assertEquals(400, $img2->height());

        @unlink($path1);
        @unlink($path2);
    }

    public function test_promotes_temp_upload_by_raw_uuid_and_deletes_temp_file(): void
    {
        $realPath = $this->createFakeImage('uuid-test.jpg');
        $file = new UploadedFile($realPath, 'uuid-test.jpg', 'image/jpeg', null, true);

        // Step 1: Upload to temporary storage
        $temp = Upload::temp($file);
        $this->assertTrue(Storage::disk('local')->exists($temp->path));

        // Extract just the UUID without directory or extension
        $uuid = pathinfo($temp->path, PATHINFO_FILENAME);

        // Step 2: Promote using ONLY the UUID
        $final = Upload::fromTemp($uuid)->save('promoted');
        $this->assertTrue(Storage::disk('public')->exists($final->path));

        // Temporary file in local disk MUST be automatically cleaned up!
        $this->assertFalse(Storage::disk('local')->exists($temp->path));

        @unlink($realPath);
    }

    public function test_temporary_upload_sanitizes_files_and_blocks_malicious_scripts(): void
    {
        $this->expectException(\KaziOmar\SmartUpload\Exceptions\SecurityException::class);

        $badFile = UploadedFile::fake()->create('backdoor.php', 10);
        Upload::temp($badFile);
    }

    public function test_from_temp_blocks_arbitrary_path_traversal_outside_temp_dir(): void
    {
        $this->expectException(\KaziOmar\SmartUpload\Exceptions\SecurityException::class);

        Upload::fromTemp('../../../etc/passwd');
    }

    public function test_url_formatter_handles_all_path_varieties_and_external_links(): void
    {
        // 1. Standard storage relative path
        $url1 = Upload::url('avatars/pic.jpg');
        $this->assertEquals(Storage::disk('public')->url('avatars/pic.jpg'), $url1);

        // 2. External HTTP/HTTPS links preserved untouched
        $external = 'https://images.unsplash.com/photo-12345?auto=format';
        $this->assertEquals($external, Upload::url($external));

        // 3. Default directory prefixing when only a filename is passed
        $url3 = Upload::url('logo.png', defaultDir: 'stores/logos');
        $this->assertEquals(Storage::disk('public')->url('stores/logos/logo.png'), $url3);

        // 4. Strips redundant storage/ prefix
        $url4 = Upload::url('storage/banners/hero.webp');
        $this->assertEquals(Storage::disk('public')->url('banners/hero.webp'), $url4);

        // 5. Fallback URL on null or empty string
        $fallback = 'https://example.com/default-avatar.png';
        $this->assertEquals($fallback, Upload::url(null, fallback: $fallback));
        $this->assertEquals($fallback, Upload::url('', fallback: $fallback));
        $this->assertNull(Upload::url(null));

        // 6. Custom disk routing (e.g. S3)
        $urlS3 = Upload::url('private/contract.pdf', disk: 's3');
        $this->assertEquals(Storage::disk('s3')->url('private/contract.pdf'), $urlS3);

        // 7. Path traversal blocking (returns fallback)
        $this->assertEquals($fallback, Upload::url('../../etc/passwd', fallback: $fallback));

        // 8. Global helper function upload_url()
        $this->assertEquals($url1, upload_url('avatars/pic.jpg'));
    }

    public function test_files_method_always_returns_upload_collection_even_for_single_file(): void
    {
        $path = $this->createFakeImage('single_in_batch.jpg');
        $file = new UploadedFile($path, 'single_in_batch.jpg', 'image/jpeg', null, true);

        // Upload a single file using the plural files() method
        $collection = Upload::files([$file])->save('batch');

        $this->assertInstanceOf(UploadCollection::class, $collection);
        $this->assertCount(1, $collection);
        $this->assertIsArray($collection->paths());
        $this->assertCount(1, $collection->paths());
        $this->assertEquals($collection->first()->path, $collection->path);
        $this->assertEquals($collection->first()->url, $collection->url);

        @unlink($path);
    }

    public function test_polymorphic_dto_properties_and_methods_prevent_crashes(): void
    {
        $path = $this->createFakeImage('test_poly.jpg');
        $file = new UploadedFile($path, 'test_poly.jpg', 'image/jpeg', null, true);

        $result = Upload::file($file)->save('single');

        // UploadResult should safely support paths() and urls() returning array
        $this->assertInstanceOf(UploadResult::class, $result);
        $this->assertIsArray($result->paths());
        $this->assertEquals([$result->path], $result->paths());
        $this->assertIsArray($result->urls());
        $this->assertEquals([$result->url], $result->urls());

        @unlink($path);
    }

    public function test_upload_request_helper_handles_single_multiple_and_empty_requests(): void
    {
        // 1. Single file request
        $path1 = $this->createFakeImage('req1.jpg');
        $file1 = new UploadedFile($path1, 'req1.jpg', 'image/jpeg', null, true);
        $request1 = \Illuminate\Http\Request::create('/upload', 'POST', [], [], ['photo' => $file1]);

        $res1 = Upload::request($request1, 'photo')->save('request_single');
        $this->assertInstanceOf(UploadResult::class, $res1);
        $this->assertStringContainsString('request_single', $res1->path);
        @unlink($path1);

        // 2. Multiple files request
        $path2 = $this->createFakeImage('req2.jpg');
        $path3 = $this->createFakeImage('req3.jpg');
        $file2 = new UploadedFile($path2, 'req2.jpg', 'image/jpeg', null, true);
        $file3 = new UploadedFile($path3, 'req3.jpg', 'image/jpeg', null, true);
        $request2 = \Illuminate\Http\Request::create('/upload', 'POST', [], [], ['photos' => [$file2, $file3]]);

        $res2 = Upload::request($request2, 'photos')->save('request_batch');
        $this->assertInstanceOf(UploadCollection::class, $res2);
        $this->assertCount(2, $res2->paths());
        @unlink($path2);
        @unlink($path3);

        // 3. Empty request (user selected no files) -> returns empty collection with empty paths array!
        $emptyRequest = \Illuminate\Http\Request::create('/upload', 'POST', [], [], []);
        $res3 = Upload::request($emptyRequest, 'photos')->save('request_empty');
        $this->assertInstanceOf(UploadCollection::class, $res3);
        $this->assertEmpty($res3->paths());
        $this->assertEquals([], $res3->paths());
    }

    public function test_delete_blocks_deleting_local_files_from_foreign_external_urls(): void
    {
        // Setup local victim file
        Storage::disk('public')->put('avatars/victim_user.webp', 'victim-content');
        $this->assertTrue(Storage::disk('public')->exists('avatars/victim_user.webp'));

        // Attempt to delete with an external foreign host URL that shares the same path
        $foreignUrl = 'https://malicious-external-domain.com/storage/avatars/victim_user.webp';
        $result = Upload::delete($foreignUrl);

        // Must reject deletion and keep local victim file intact!
        $this->assertFalse($result);
        $this->assertTrue(Storage::disk('public')->exists('avatars/victim_user.webp'));

        // Legitimate deletion using local URL must succeed
        $localUrl = Storage::disk('public')->url('avatars/victim_user.webp');
        $this->assertTrue(Upload::delete($localUrl));
        $this->assertFalse(Storage::disk('public')->exists('avatars/victim_user.webp'));
    }

    public function test_url_formatter_restricts_unsafe_data_uris(): void
    {
        // Safe data:image/ is preserved
        $safeData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $this->assertEquals($safeData, Upload::url($safeData));

        // Unsafe data:text/html must be rejected (fallback returned)
        $unsafeData = 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==';
        $fallback = 'https://example.com/fallback.png';
        $this->assertEquals($fallback, Upload::url($unsafeData, fallback: $fallback));
    }

    public function test_delete_blocks_path_traversal_attempts(): void
    {
        // Path traversal attempts must return false without throwing 500 exceptions
        $this->assertFalse(Upload::delete('../../etc/passwd'));
        $this->assertFalse(Upload::delete('avatars/../secrets.env'));
        $this->assertFalse(Upload::delete("avatars/pic.jpg\0"));
    }
}

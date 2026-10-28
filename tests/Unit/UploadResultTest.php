<?php

namespace KaziOmar\SmartUpload\Tests\Unit;

use Illuminate\Support\Facades\Storage;
use KaziOmar\SmartUpload\DTO\UploadResult;
use KaziOmar\SmartUpload\Tests\TestCase;

class UploadResultTest extends TestCase
{
    public function test_formats_sizes_correctly(): void
    {
        $kbResult = new UploadResult('p', 'u', 'f', 'o', 'e', 'm', 2048, 'public');
        $this->assertEquals('2 KB', $kbResult->sizeFormatted());

        $mbResult = new UploadResult('p', 'u', 'f', 'o', 'e', 'm', 2097152, 'public');
        $this->assertEquals('2 MB', $mbResult->sizeFormatted());
    }

    public function test_converts_to_array_and_json(): void
    {
        $result = new UploadResult(
            path: 'avatars/pic.webp',
            url: 'https://app.test/storage/avatars/pic.webp',
            filename: 'pic.webp',
            originalName: 'pic.png',
            extension: 'webp',
            mimeType: 'image/webp',
            size: 1024,
            disk: 'public'
        );

        $array = $result->toArray();
        $this->assertEquals('avatars/pic.webp', $array['path']);
        $this->assertEquals('1 KB', $array['size_formatted']);

        $json = $result->toJson();
        $this->assertJson($json);
        $this->assertStringContainsString('avatars/pic.webp', $json);
    }

    public function test_deletes_file_from_storage(): void
    {
        Storage::disk('public')->put('avatars/delete-me.jpg', 'content');
        $this->assertTrue(Storage::disk('public')->exists('avatars/delete-me.jpg'));

        $result = new UploadResult(
            path: 'avatars/delete-me.jpg',
            url: 'http://localhost/avatars/delete-me.jpg',
            filename: 'delete-me.jpg',
            originalName: 'delete-me.jpg',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            size: 7,
            disk: 'public'
        );

        $this->assertTrue($result->delete());
        $this->assertFalse(Storage::disk('public')->exists('avatars/delete-me.jpg'));
    }
}

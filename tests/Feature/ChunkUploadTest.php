<?php

namespace KaziOmar\SmartUpload\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use KaziOmar\SmartUpload\Facades\Upload;
use KaziOmar\SmartUpload\Tests\TestCase;

class ChunkUploadTest extends TestCase
{
    public function test_assembles_multi_part_chunks_into_final_file(): void
    {
        $uuid = 'chunk-test-12345';
        $fullContent = 'Part1-FirstChunkContent.Part2-SecondChunkContent.Part3-ThirdChunkContent.';

        $part1 = 'Part1-FirstChunkContent.';
        $part2 = 'Part2-SecondChunkContent.';
        $part3 = 'Part3-ThirdChunkContent.';

        // Chunk 0
        $tmp1 = tempnam(sys_get_temp_dir(), 'p1_');
        file_put_contents($tmp1, $part1);
        $file1 = new UploadedFile($tmp1, 'video.mp4', 'video/mp4', null, true);
        $req1 = Request::create('/upload', 'POST', [
            'uuid' => $uuid,
            'chunk' => 0,
            'total_chunks' => 3,
            'filename' => 'large-video.mp4',
        ], [], ['file' => $file1]);

        $chunker1 = Upload::chunk($req1);
        $this->assertFalse($chunker1->isComplete());
        $this->assertEquals(33.33, $chunker1->percentage());

        // Chunk 1
        $tmp2 = tempnam(sys_get_temp_dir(), 'p2_');
        file_put_contents($tmp2, $part2);
        $file2 = new UploadedFile($tmp2, 'video.mp4', 'video/mp4', null, true);
        $req2 = Request::create('/upload', 'POST', [
            'uuid' => $uuid,
            'chunk' => 1,
            'total_chunks' => 3,
            'filename' => 'large-video.mp4',
        ], [], ['file' => $file2]);

        $chunker2 = Upload::chunk($req2);
        $this->assertFalse($chunker2->isComplete());
        $this->assertEquals(66.67, $chunker2->percentage());

        // Chunk 2 (Final chunk)
        $tmp3 = tempnam(sys_get_temp_dir(), 'p3_');
        file_put_contents($tmp3, $part3);
        $file3 = new UploadedFile($tmp3, 'video.mp4', 'video/mp4', null, true);
        $req3 = Request::create('/upload', 'POST', [
            'uuid' => $uuid,
            'chunk' => 2,
            'total_chunks' => 3,
            'filename' => 'large-video.mp4',
        ], [], ['file' => $file3]);

        $chunker3 = Upload::chunk($req3);
        $this->assertTrue($chunker3->isComplete());
        $this->assertEquals(100.0, $chunker3->percentage());

        // Save & merge to target directory
        $result = $chunker3->save('videos');

        $this->assertTrue(Storage::disk('public')->exists($result->path));
        $savedContent = Storage::disk('public')->get($result->path);
        $this->assertEquals($fullContent, $savedContent);

        @unlink($tmp1);
        @unlink($tmp2);
        @unlink($tmp3);
    }

    public function test_throws_exception_if_attempting_to_save_incomplete_chunk(): void
    {
        $this->expectException(\KaziOmar\SmartUpload\Exceptions\ChunkException::class);
        $this->expectExceptionMessage('Cannot save: Upload is only');

        $tmp = tempnam(sys_get_temp_dir(), 'p1_');
        file_put_contents($tmp, 'partial');
        $file = new UploadedFile($tmp, 'incomplete.mp4', 'video/mp4', null, true);

        $req = Request::create('/upload', 'POST', [
            'uuid' => 'incomplete-uuid-999',
            'chunk' => 0,
            'total_chunks' => 5,
            'filename' => 'incomplete.mp4',
        ], [], ['file' => $file]);

        $chunker = Upload::chunk($req);
        $this->assertFalse($chunker->isComplete());

        try {
            $chunker->save('videos');
        } finally {
            @unlink($tmp);
        }
    }
}

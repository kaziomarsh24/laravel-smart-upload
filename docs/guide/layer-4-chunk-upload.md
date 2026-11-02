# Layer 4: Resumable Chunk Upload Engine

For multi-gigabyte video or archive uploads, sending the entire file in a single HTTP request causes timeouts, connection resets, and excessive memory spikes.

Smart Upload includes a client-agnostic chunk upload manager with sequential stream merging.

---

### Basic Controller Usage

```php
use Illuminate\Http\Request;
use KaziOmar\SmartUpload\Facades\Upload;

public function uploadChunk(Request $request)
{
    $chunk = Upload::chunk($request);

    if ($chunk->isComplete()) {
        // All parts arrived! Merges parts into the final destination:
        $video = $chunk->save('videos');

        return response()->json([
            'completed' => true,
            'video_url' => $video->url,
            'size'      => $video->sizeFormatted(),
        ]);
    }

    // Still receiving parts:
    return response()->json([
        'completed'  => false,
        'percentage' => $chunk->percentage(),
    ]);
}
```

---

### Client-Agnostic Parameter Compatibility

`Upload::chunk()` automatically detects incoming parameters from popular chunking libraries:

- **Dropzone.js**: `dzuuid`, `dzchunkindex`, `dztotalchunkcount`, `dzfilename`
- **Resumable.js**: `resumableIdentifier`, `resumableChunkNumber`, `resumableTotalChunks`
- **Standard**: `uuid`, `chunk`, `total_chunks`, `filename`

---

### Low-Memory Stream Merging

Instead of buffering multiple megabytes in PHP RAM (`file_get_contents`), Smart Upload opens low-level sequential file streams (`fopen`, `stream_copy_to_stream`) to stitch chunks directly on disk. You can merge a 10GB video while consuming less than 15MB of PHP memory!


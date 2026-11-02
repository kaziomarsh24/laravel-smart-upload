# Quickstart in 10 Seconds

### Approach A: The Fluent Facade (Direct Controller Upload)

```php
use App\Models\User;
use Illuminate\Http\Request;
use KaziOmar\SmartUpload\Facades\Upload;

public function store(Request $request)
{
    // 1. Single file from request: Auto WebP, downscaled, safely named
    $avatar = Upload::request($request, 'avatar')->preset('avatar')->save('avatars');

    // 2. Or batch files with zero-boilerplate:
    $photos = Upload::request($request, 'photos')->toWebp()->save('reviews')->paths();

    return response()->json([
        'avatar_url' => $avatar->url,
        'photos'     => $photos, // Array of paths or [] if none provided
    ]);
}
```

---

### Approach B: Updating Existing Eloquent Models

```php
// Automatically purges old avatar on disk, converts new image to WebP, and scales to max 300x300:
$result = Upload::file($request->file('avatar'))
    ->replace($user->avatar)
    ->preset('avatar')
    ->save('avatars');

$user->update(['avatar' => $result->path]);
```

---

### What does `save()` return?

Calling `->save()` on a single file returns an immutable **`UploadResult`** DTO:

```php
$result->path;           // "avatars/a7f9c2e1_sample.webp"
$result->url;            // "https://your-domain.com/storage/avatars/a7f9c2e1_sample.webp"
$result->filename;       // "a7f9c2e1_sample.webp"
$result->originalName;   // "my-photo.jpg"
$result->extension;      // "webp"
$result->mimeType;       // "image/webp"
$result->size;           // 48512 (bytes)
$result->sizeFormatted(); // "47.38 KB"
$result->variants;       // ['thumb' => 'avatars/xxx_thumb.webp', ...]
```


# Layer 1: Fluent Upload Engine (`Upload::`)

Smart Upload provides an order-independent fluent builder for handling uploads in Laravel. Method order does not matter; operations execute in an optimal sequence only when `->save()` is called.

---

### 1.1 Single Image Upload (WebP & Compression)

By default, uploaded images are automatically converted to modern WebP format and compressed to quality `85`.

```php
use KaziOmar\SmartUpload\Facades\Upload;

// Convert to WebP with custom quality (1-100):
$result = Upload::file($request->file('avatar'))
    ->toWebp(90)
    ->save('avatars');

// Or preserve original format (e.g. animated GIFs, lossless PNG logos):
$result = Upload::file($request->file('logo'))
    ->keepOriginalFormat()
    ->save('logos');
```

---

### 1.2 Aspect Ratio Downscaling vs Center Crop (`scaleDown` vs `fit`)

Smart Upload distinguishes between proportional scaling and exact dimension cropping:

```php
// Proportional downscale: preserves aspect ratio, never upscales smaller images
$result = Upload::file($request->file('cover'))
    ->scaleDown(1200, 800)
    ->save('covers');

// Center-crop: smartly crops edges to match exact dimensions
$result = Upload::file($request->file('avatar'))
    ->fit(300, 300)
    ->save('avatars');
```

::: tip
Use `scaleDown` for banners, article headers, and galleries where the full photo should remain visible. Use `fit` for square profile photos, thumbnails, and cards that require strict uniform dimensions.
:::

---

### 1.3 Named Presets (`->preset()`)

Standardize upload rules in `config/smart-upload.php` and apply them by name:

```php
$result = Upload::file($request->file('photo'))
    ->preset('avatar') // Applies configured sizing, WebP conversion, and quality
    ->save('avatars');
```

---

### 1.4 Multi-Size Responsive Variants (`->variants()`)

Generate multiple image sizes in a single memory-optimized cascade pass. Variants follow SEO-friendly naming (`{slug}_{token}_{variant}.webp`) and are ordered from largest to smallest to minimize memory consumption:

```php
$result = Upload::file($request->file('banner'))
    ->variants([
        'thumb'   => ['fit' => [150, 150]],
        'mobile'  => ['scale_down' => 480],
        'tablet'  => ['scale_down' => 768],
        'desktop' => ['scale_down' => 1200],
    ])
    ->save('banners');

// Relative paths map:
// $result->path                 => "banners/hero_a8Fk.webp"
// $result->variants['mobile']    => "banners/hero_a8Fk_mobile.webp"
// $result->variants['thumb']     => "banners/hero_a8Fk_thumb.webp"

// Fully qualified URLs map:
$urls = $result->variantUrls();
// $urls['mobile'] => "https://example.com/storage/banners/hero_a8Fk_mobile.webp"
```

In Blade templates, use the global `upload_url()` helper to load variants cleanly:

```blade
<picture>
    <source media="(max-width: 480px)" srcset="{{ upload_url($banner->image, variant: 'mobile') }}">
    <source media="(max-width: 768px)" srcset="{{ upload_url($banner->image, variant: 'tablet') }}">
    <img src="{{ upload_url($banner->image) }}" alt="Banner">
</picture>
```

::: info
If you define custom variant keys beyond the defaults (such as `og` or `card`), add them to `'variant_names'` in `config/smart-upload.php` so model lifecycle cleanup can purge them automatically.
:::

---

### 1.5 Old File Replacement (`->replace()`)

When updating an existing file without Eloquent model events, pass the previous path to `->replace()`. Smart Upload deletes the old file and all associated variant siblings upon successful upload:

```php
$result = Upload::file($request->file('avatar'))
    ->replace($user->avatar)
    ->preset('avatar')
    ->save('avatars');

$user->update(['avatar' => $result->path]);
```

::: tip
If your model uses the `HasUploadPurge` trait, you do not need `->replace()`. The trait automatically cleans up old files upon model updates.
:::

---

### 1.6 Request Auto-Detection (`Upload::request()`) & Batch Uploads

`Upload::request()` inspects incoming HTTP request fields, automatically determining whether the input is a single file or an array of files:

```php
// Handles single uploads ($request->file('avatar')):
$avatar = Upload::request($request, 'avatar')->preset('avatar')->save('avatars');

// Handles batch uploads ($request->file('photos')):
$collection = Upload::request($request, 'photos')->toWebp()->save('gallery');

$collection->paths(); // Array of all saved paths: ['gallery/1.webp', 'gallery/2.webp']
$collection->urls();  // Array of public URLs
$collection->count(); // Total processed count
```

You can also pass arrays directly using `Upload::files($request->file('photos'))`, which always returns an `UploadCollection`.

---

### 1.7 Universal URL Formatter (`Upload::url()` & `upload_url()`)

Convert stored database paths into fully qualified URLs across Local, AWS S3, Cloudflare R2, or DigitalOcean Spaces:

```php
// 1. Storage relative path (auto-resolves storage disk prefix):
$url = Upload::url($user->avatar);

// 2. External links preserved untouched (OAuth avatars, CDNs, Unsplash):
$url = Upload::url('https://lh3.googleusercontent.com/avatar.jpg');

// 3. Auto-prefix default directory when path is only a filename:
$url = Upload::url('logo.png', defaultDir: 'stores/logos');

// 4. Fallback placeholder for null or empty values:
$url = Upload::url($user->avatar, fallback: 'https://placehold.co/150x150.png');

// 5. Global helper function anywhere in views:
echo upload_url($product->image, variant: 'thumb');
```

---

### 1.8 Polymorphic File Deletion (`Upload::delete()`)

`Upload::delete()` safely removes files without throwing unhandled exceptions if the file is already gone:

```php
// 1. Delete by relative path:
Upload::delete('avatars/old_pic.webp');

// 2. Delete by full public URL (path is parsed automatically):
Upload::delete('https://example.com/storage/avatars/old_pic.webp');

// 3. Delete arrays of paths ($product->photos):
Upload::delete(['products/1.webp', 'products/2.webp']);

// 4. Delete associative variant maps:
Upload::delete($banner->images); // ['desktop' => '...', 'mobile' => '...']

// 5. Delete directly from DTO instances:
$uploadResult->delete();     // Deletes the base file and all sibling variants
$uploadCollection->delete(); // Batch deletes all uploaded files
```

::: info
Associative metadata arrays that do not contain valid file paths (such as `['label' => 'Logo']`) are ignored, preventing accidental root file deletions.
:::

---

### 1.9 Cloud Disk Routing (`->disk()`)

By default, files are saved to the disk configured in `smart-upload.php` (`public`). You can override the destination disk per upload:

```php
$result = Upload::file($request->file('document'))
    ->disk('s3')
    ->save('contracts');
```

---

### 1.10 Private File Downloads, Sandboxed Preview & Signed URLs

Confidential documents (such as invoices, identity documents, and contracts) should be stored on private storage (`local` disk in `storage/app/` or private cloud buckets):

```php
// 1. Force browser download with a custom filename:
return Upload::download('contracts/sample.pdf', filename: 'Service_Agreement.pdf', disk: 'local');

// 2. Stream inline preview with strict CSP sandbox and nosniff headers:
return Upload::response('invoices/inv_102.pdf', disk: 'local');

// 3. Generate a temporary expiring signed URL for private S3 / Cloudflare R2:
$signedUrl = Upload::temporaryUrl('tax/form.pdf', expiration: 15, disk: 's3');
return redirect($signedUrl);
```

::: warning
`Upload::response()` dynamically verifies the true binary MIME type (`application/pdf`). Non-PDF files or disguised HTML payloads are automatically rendered inside a Content Security Policy sandbox (`sandbox allow-scripts=none`) to eliminate stored XSS risks.
:::

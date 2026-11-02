# Layer 1: Fluent Upload Engine (`Upload::`)

### 1.1 Single Image Upload (WebP & Compression)

By default, any uploaded image is automatically converted to modern WebP format and compressed to quality `85` (configurable).

```php
// Convert to WebP with custom quality 90:
$result = Upload::file($request->file('avatar'))
    ->toWebp(90)
    ->save('avatars');

// Or keep original format (e.g. keep PNG transparency):
$result = Upload::file($request->file('logo'))
    ->keepOriginalFormat()
    ->save('logos');
```

---

### 1.2 Smart Image Sizing (`scaleDown` vs `fit`)

```php
// Scale down preserving aspect ratio (never upscales smaller images):
$result = Upload::file($request->file('cover'))
    ->scaleDown(1200, 800)
    ->save('covers');

// Center-crop to exact dimensions (perfect for avatars or thumbnails):
$result = Upload::file($request->file('avatar'))
    ->fit(300, 300)
    ->save('avatars');
```

---

### 1.3 Named Presets (`->preset()`)

Define standard rules in `config/smart-upload.php` and invoke by name:

```php
$result = Upload::file($request->file('photo'))
    ->preset('avatar') // Applies [300, 300] fit, WebP, Q85
    ->save('avatars');
```

---

### 1.4 Multi-Size Responsive Variants (`->variants()`)

Generate responsive image variants in a single memory-optimized cascade pass:

```php
$result = Upload::file($request->file('banner'))
    ->variants([
        'thumb'  => ['fit' => [150, 150], 'quality' => 80],
        'medium' => ['scale_down' => [600, 400], 'quality' => 85],
    ])
    ->save('banners');

// Access generated variants:
$result->variants['thumb'];  // "banners/xxx_thumb.webp"
$result->variants['medium']; // "banners/xxx_medium.webp"
```

---

### 1.5 Old File Replacement (`->replace()`)

When updating an image, passing the previous file path to `->replace()` ensures the old file and its associated variants are deleted from storage:

```php
$result = Upload::file($request->file('avatar'))
    ->replace($user->avatar)
    ->save('avatars');
```

---

### 1.6 Batch Multiple Files Upload

```php
// Automatically handles arrays of uploaded files:
$collection = Upload::request($request, 'photos')
    ->preset('thumbnail')
    ->save('gallery');

$collection->paths(); // Array of all saved paths
$collection->urls();  // Array of all public URLs
$collection->count(); // Total files processed
```

---

### 1.7 Universal URL Formatter & Polymorphic Deletion

```php
// Format any path or filename into a full URL:
$url = Upload::url('avatars/photo.webp');

// Delete a single path, URL, array of paths, or variant maps safely:
Upload::delete('avatars/photo.webp');
```

---

### 1.8 Routing to Cloud Disks (`->disk()`)

By default, files are saved to the disk configured in `smart-upload.php` (`public`). You can override this per upload:

```php
$result = Upload::file($request->file('report'))
    ->disk('s3')
    ->save('reports');
```

---

### 1.9 Non-Image Files, Downloads & Temporary Signed URLs

Smart Upload automatically detects non-image documents (PDFs, ZIPs, DOCX). It skips image manipulation and directly applies security inspection, safe renaming, and storage:

```php
// Upload PDF or document:
$doc = Upload::file($request->file('contract'))->save('contracts');

// Stream download with custom filename and headers:
return Upload::download('contracts/sample.pdf', 'Terms-and-Conditions.pdf');

// Generate temporary signed cloud URL (S3, Cloudflare R2, Google Cloud Storage):
$url = Upload::temporaryUrl('contracts/sample.pdf', now()->addMinutes(30));
```


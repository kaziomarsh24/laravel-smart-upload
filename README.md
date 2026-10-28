# Laravel Smart Upload 🚀

[![Latest Version on Packagist](https://img.shields.io/packagist/v/thekaziomar/laravel-smart-upload.svg?style=flat-square)](https://packagist.org/packages/thekaziomar/laravel-smart-upload)
[![Total Downloads](https://img.shields.io/packagist/dt/thekaziomar/laravel-smart-upload.svg?style=flat-square)](https://packagist.org/packages/thekaziomar/laravel-smart-upload)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%20--%208.5+-777bb4.svg?style=flat-square)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-ff2d20.svg?style=flat-square)](https://laravel.com)

A developer-first, zero-database file and chunk upload toolkit for Laravel with built-in image optimization, strict security, and database rollback safeguards.

---

## 🌟 Why Laravel Smart Upload?

* **Zero Database Dependency:** Works completely standalone without migrations or extra tables (unlike Spatie MediaLibrary).
* **Extreme Developer Convenience (DX):** Fluent, order-independent method chaining (`Upload::file()->...->save()`).
* **Intervention Image v3 Engine:** Auto-selects Imagick or GD, handles WebP/AVIF conversions, smart cropping (`fit()`), and responsive aspect-ratio downscaling (`scaleDown()`).
* **Ghost File Shield (Atomic Transactions):** Automatically purges uploaded files if a `DB::transaction()` fails or rolls back.
* **Resumable Chunk Uploads:** Client-agnostic streaming chunk assembler designed for large files and videos with low memory usage.
* **Model Auto-Purge Trait:** Automatically deletes old files from disk/S3 when an Eloquent model is updated or deleted.
* **Two-Step / Temporary Uploads:** Seamless support for mobile apps, React, Vue, Flutter, and Livewire dropzones.
* **Strict Security by Default:** True magic-bytes MIME inspection, EXIF privacy stripping, and dangerous executable blocking.

---

## 📦 Installation

Install the package via Composer:

```bash
composer require thekaziomar/laravel-smart-upload
```

Optionally publish the configuration file:

```bash
php artisan vendor:publish --tag="smart-upload-config"
```

---

## 🚀 Quickstart & Usage

### 1. Single Image Upload (Automatic WebP & Scale)

```php
use KaziOmar\SmartUpload\Facades\Upload;

// Automatically downscaled and converted to WebP
$result = Upload::file($request->file('avatar'))
    ->scaleDown(800, 600)
    ->toWebp(quality: 85)
    ->save('avatars');

return response()->json([
    'url'  => $result->url,
    'path' => $result->path,
    'size' => $result->sizeFormatted(), // e.g. "450 KB"
]);
```

### 2. Auto-Clean Old File on Replace

```php
// Automatically deletes the old avatar from storage when the new one is saved
$result = Upload::file($request->file('avatar'))
    ->replace($user->avatar_path)
    ->preset('avatar')
    ->save('avatars');

$user->update(['avatar_path' => $result->path]);
```

### 3. Named Presets

Configure presets in `config/smart-upload.php` and invoke with a single word:

```php
// Uses config presets (fit 300x300, webp 85%):
$result = Upload::file($request->file('photo'))->preset('avatar')->save('avatars');
```

### 4. Multiple Files Batch Upload

```php
$collection = Upload::files($request->file('gallery'))
    ->toWebp()
    ->save('gallery');

// Returns an UploadCollection:
$paths = $collection->paths(); // array of paths
$urls  = $collection->urls();  // array of URLs
```

### 5. Large File Chunked Uploads

Seamlessly handles multi-part chunks sent from Dropzone, Resumable.js, or mobile apps:

```php
public function uploadChunk(Request $request)
{
    $chunk = Upload::chunk($request);

    if ($chunk->isComplete()) {
        // All parts arrived! Stream-merges and moves to final destination:
        $result = $chunk->save('videos');
        
        return response()->json([
            'status' => 'complete',
            'url'    => $result->url,
            'path'   => $result->path,
        ]);
    }

    return response()->json([
        'status'   => 'uploading',
        'progress' => $chunk->percentage(), // e.g. 66.67
    ]);
}
```

### 6. Atomic File Shield (DB Rollback Protection)

No ghost files when a database operation fails!

```php
DB::transaction(function () use ($request) {
    // 1. Upload file to S3/Disk
    $result = Upload::file($request->file('invoice'))->save('invoices');

    // 2. Database record creation
    $invoice = Invoice::create(['path' => $result->path, ...]);

    // If an exception is thrown here, the database rolls back
    // AND the uploaded invoice file is AUTOMATICALLY deleted from disk!
});
```

### 7. Eloquent Model Auto-Purge Trait

Add `HasUploadPurge` to your model:

```php
use Illuminate\Database\Eloquent\Model;
use KaziOmar\SmartUpload\Traits\HasUploadPurge;

class User extends Model
{
    use HasUploadPurge;

    // These file attributes will be auto-purged from storage when updated or deleted:
    protected array $purgeableFiles = ['avatar', 'cover_photo'];
}
```

### 8. Temporary Two-Step Upload (Mobile / React / Flutter)

```php
// Step 1: Upload to temp storage when user picks an image
$temp = Upload::temp($request->file('file'));
return response()->json(['temp_path' => $temp->path]);

// Step 2: In your final form submission controller:
$result = Upload::fromTemp($request->temp_path)
    ->toWebp()
    ->save('products');
```

---

## 🧹 Garbage Collection

The package includes an Artisan command to clear expired temporary files and abandoned chunk uploads:

```bash
php artisan smart-upload:prune --hours=24
```

> **Note:** This command is automatically scheduled daily by default via the service provider.

---

## 🧪 Testing

Run the automated test suite with PHPUnit:

```bash
composer test
# or
vendor/bin/phpunit
```

---

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

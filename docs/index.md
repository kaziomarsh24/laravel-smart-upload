---
layout: home

hero:
  name: "Smart Upload"
  text: "Clean, painless file uploads for Laravel."
  tagline: '<span class="hero-badge">v1.3 • Laravel 11, 12, & 13</span><br>Automatic WebP optimization, center-cropping, chunked video streaming, and zero extra database tables. Upload files with one expressive line of code.'
  image:
    src: /logo.svg
    alt: Laravel Smart Upload
  actions:
    - theme: brand
      text: Get Started
      link: /guide/getting-started
    - theme: alt
      text: Quickstart
      link: /guide/quickstart
    - theme: alt
      text: GitHub
      link: https://github.com/kaziomarsh24/laravel-smart-upload

features:
  - icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/></svg>'
    title: Zero Database Bloat
    details: Store simple file paths in standard string or JSON columns. No extra tables, no polymorphic joins, and no database migrations.
  - icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>'
    title: Effortless WebP & Sizing
    details: Converts images to WebP on upload, compresses them sensibly, and downscales without stretching smaller images.
  - icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.5 3.8 17 5 19 5a1 1 0 0 1 1 1z"/></svg>'
    title: No Ghost Files on Failure
    details: If an upload happens inside a database transaction that rolls back, the uploaded file is automatically purged from disk or S3.
  - icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>'
    title: Two-Step Mobile & SPA Flow
    details: Upload files in the background while the user fills out a form. When they submit, promote the temporary file in milliseconds.
  - icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>'
    title: Low-Memory Chunk Streams
    details: Stream multi-gigabyte videos in small parts using Dropzone or Resumable.js with almost zero server memory usage.
  - icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>'
    title: Secure by Default
    details: Validates true file magic bytes, strips sensitive EXIF metadata, and blocks malicious executable extensions out of the box.
---

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-number">0</div>
    <div class="stat-label">Extra Tables</div>
    <div class="stat-desc">Clean database, zero migrations</div>
  </div>
  <div class="stat-card">
    <div class="stat-number">~85%</div>
    <div class="stat-label">Smaller Images</div>
    <div class="stat-desc">Automated WebP compression</div>
  </div>
  <div class="stat-card">
    <div class="stat-number">&lt; 20ms</div>
    <div class="stat-label">Form Submit</div>
    <div class="stat-desc">Two-step temporary uploads</div>
  </div>
  <div class="stat-card">
    <div class="stat-number">&lt; 6MB</div>
    <div class="stat-label">Memory Peak</div>
    <div class="stat-desc">Streamed video chunk assembly</div>
  </div>
</div>

## Common Recipes

::: code-group

```php [Upload & Replace]
use App\Models\User;
use KaziOmar\SmartUpload\Facades\Upload;

// Resize to 300x300, convert to WebP, delete the old avatar from disk, and save
$result = Upload::file($request->file('avatar'))
    ->replace($user->avatar)
    ->fit(300, 300)
    ->save('avatars');

$user->update(['avatar' => $result->path]);
```

```php [Two-Step (React / Mobile)]
use KaziOmar\SmartUpload\Facades\Upload;

// 1. Upload in the background when the user picks a file:
$temp = Upload::temp($request->file('avatar'));
// Returns: {"filename": "tmp_a8f9c1b2.jpg", "url": "..."}

// 2. Fast JSON submit when the user clicks 'Save Profile':
$final = Upload::fromTemp($request->string('avatar_token'))
    ->fit(300, 300)
    ->save('avatars');

$user->update(['avatar' => $final->path]);
```

```php [Large Video Chunks]
use KaziOmar\SmartUpload\Facades\Upload;

// Handles chunks 1, 2, 3... with zero memory buildup
$chunk = Upload::chunk($request);

if ($chunk->isComplete()) {
    // Merges chunks directly on disk using PHP streams (~5MB RAM)
    $video = $chunk->save('videos');
    
    return response()->json(['path' => $video->path]);
}

return response()->json(['progress' => $chunk->progress()]);
```

```php [Database Transactions]
use Illuminate\Support\Facades\DB;
use KaziOmar\SmartUpload\Facades\Upload;

DB::transaction(function () use ($request) {
    $receipt = Upload::file($request->file('receipt'))->save('receipts');

    Invoice::create([
        'receipt_path' => $receipt->path,
        'total'        => $request->total,
    ]);

    // If payment fails and throws here, the uploaded file is
    // automatically deleted from disk or S3. No ghost files!
    PaymentService::charge();
});
```

:::

<div class="comparison-section">

<div class="comparison-header">
  <h2>How it compares</h2>
  <p>Why developers choose Smart Upload over bloated media packages or manual boilerplate.</p>
</div>

<div class="comparison-table-wrapper">

| Feature | Native Laravel | Spatie MediaLibrary | Smart Upload |
| :--- | :---: | :---: | :---: |
| **Extra database tables** | None | 1–3 tables | **None** |
| **API Style** | Basic procedural | Coupled to models | **Fluent chaining** |
| **WebP & Sizing** | Manual GD code | Complex conversions | **One-liner** |
| **Purge on DB rollback** | No (files stay orphaned) | No | **Automatic** |
| **Two-step SPA / Mobile** | Build it yourself | Complex | **Built-in (`temp` / `fromTemp`)** |
| **Video chunk streaming** | Build it yourself | Not supported | **Built-in** |
| **Clean up on model delete** | Manual deletion code | Model events | **Single Trait** |

</div>

</div>

## Installation

Install the package via Composer:

```bash
composer require thekaziomar/laravel-smart-upload
```

Publish the config file:

```bash
php artisan vendor:publish --tag=smart-upload-config
```

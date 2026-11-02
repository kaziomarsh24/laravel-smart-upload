# Master Method Cheat Sheet & Decision Guide

Every method in the builder chain is **order-independent** and serves a specific architectural purpose. Use this quick reference to choose the right method for your requirements:

| Chained Method | When to Use It (Primary Use Case) | When NOT to Use It | Alternative / Counterpart |
| :--- | :--- | :--- | :--- |
| **`->toWebp(?$q)`** | Convert images to modern, lightweight WebP format. | Unnecessary if `'auto_webp' => true` in config (active by default). | `->keepOriginalFormat()` |
| **`->keepOriginalFormat()`** | Retain original format (animated GIFs, lossless PNG logos, legacy compatibility). | Do not use when minimal bandwidth and fast loading speeds are top priority. | Default Auto-WebP or `->toWebp()` |
| **`->quality(int $q)`** | Fine-tune compression quality (1-100). E.g. `60` for thumbnails, `90` for crisp hero images. | Unnecessary if default quality (`85`) in config is already optimal. | `->toWebp(quality: $q)` or config |
| **`->scaleDown($w, $h)`** | Banners, article heroes, and gallery photos. Downscales preserving aspect ratio; **never stretches smaller images**. | Do not use when you need an exact square crop with zero letterboxing. | `->fit($width, $height)` |
| **`->fit($w, $h)`** | Square profile avatars, thumbnails, or icons. Smartly center-crops to fill exact dimensions. | Do not use when cropping out image edges is unacceptable (e.g. panoramic landscapes). | `->scaleDown($w, $h)` |
| **`->preset($name)`** | Apply standard dimension and quality presets declared in `config/smart-upload.php`. | Unnecessary for one-off custom dimensions. | Manually chaining `->fit()->toWebp()` |
| **`->variants(array $v)`** | Generate responsive image sizes (e.g. `mobile`, `tablet`, `thumb`) in one fluent pass. | Do not use when only a single standard image resolution is needed. | `->scaleDown()` or `->fit()` |
| **`->disk($disk)`** | Route this upload to cloud storage (`s3`, `r2`, `spaces`) or private disk. | Unnecessary if storing to the default disk configured in `smart-upload.php`. | Set `'disk' => 's3'` globally in config |
| **`->replace($oldPath)`** | Delete previous file and its responsive variants upon successful save. | **Not needed if your model uses `HasUploadPurge`!** (The trait auto-deletes old files upon `$user->update()`). | Use `HasUploadPurge` trait on Model |
| **`->temporary()`** | Stage upload in isolated temporary storage during multi-step registration or SPA wizards. | Do not use for standard, immediate form submissions. | `Upload::temp($file)` one-liner |
| **`->save($directory)`** | **Terminal execution method.** Runs the optimized pipeline, verifies security, writes to disk, and arms transaction shields. | Must be called at the very end of the chain. | — |

---

## Static Facade & Helpers Quick Reference

| Static Method / Helper | Description | Return Type |
| :--- | :--- | :--- |
| `Upload::file($file)` | Start fluent builder with an uploaded file | `SmartUploader` |
| `Upload::files(array $files)` | Process multiple files as an iterable collection | `UploadCollection` |
| `Upload::request($request, $key)` | Automatically detects single or array uploads from request | `SmartUploader\|UploadCollection` |
| `Upload::temp($file)` | Instantly stage a file in temporary storage for two-step flows | `TempUploadResult` |
| `Upload::fromTemp($tempId)` | Promote a temporary upload to permanent storage | `SmartUploader` |
| `Upload::chunk($request)` | Handle incoming resumable chunk streams | `ChunkResult` |
| `Upload::url($path, $fallback?, $defaultDir?, $disk?)` | Format a stored relative path into a public URL | `string` |
| `Upload::temporaryUrl($path, $expiration, $disk?)` | Generate temporary signed cloud URL (S3/GCS/R2) | `string` |
| `Upload::download($path, $name?, $headers?, $disk?)` | Force download of a private file with custom filename | `StreamedResponse` |
| `Upload::response($path, $headers?, $disk?)` | Stream inline preview with security headers and CSP sandbox | `StreamedResponse` |
| `Upload::delete($path, $disk?)` | Safely delete a file and its responsive variants | `bool` |
| `upload_url($path, $variant?, $disk?)` | Global Blade helper to resolve public URLs or responsive variants | `string` |

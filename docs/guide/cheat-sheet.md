# Master Method Cheat Sheet & Decision Guide

Every method in the builder chain is **order-independent** and serves a specific architectural purpose. Use this quick reference to know exactly which method to pick, when it is unnecessary, and its alternative:

| Chained Method | When to Use It (Primary Use Case) | When NOT to Use It | Alternative / Counterpart |
| :--- | :--- | :--- | :--- |
| **`->toWebp(?$q)`** | Force convert any image to modern, lightweight WebP format. | Unnecessary if `'auto_webp' => true` in config (it is already active by default). | `->keepOriginalFormat()` |
| **`->keepOriginalFormat()`** | Retain original format (e.g. animated GIFs, lossless PNG brand logos, or legacy app compatibility). | Don't use when minimal bandwidth and fast loading speed are your priority. | Default Auto-WebP or `->toWebp()` |
| **`->quality(int $q)`** | Fine-tune image compression ratio (1-100). E.g. `60` for thumbnails, `90` for crisp product photos. | Unnecessary if default quality (`85`) in config is already optimal for you. | `->toWebp(quality: $q)` or config |
| **`->scaleDown($w, $h)`** | Banners, article heroes, and gallery photos. Downscales preserving aspect ratio; **never stretches smaller images**. | Don't use when you need an exact square crop with zero letterboxing. | `->fit($width, $height)` |
| **`->fit($w, $h)`** | Square profile avatars, thumbnails, or icons. Smartly center-crops to fill exact dimensions. | Don't use when cropping out image edges is unacceptable (e.g. panoramic landscapes). | `->scaleDown($w, $h)` |
| **`->preset($name)`** | Reusable sizing/quality configs (e.g. `avatar`, `banner`) declared once in `config/smart-upload.php`. | Unnecessary for one-off custom dimensions. | Manually chaining `->fit()->toWebp()` |
| **`->variants(array $v)`** | Generate responsive image sizes (e.g. `mobile`, `tablet`, `thumb`) in one fluent pass. | Don't use when only a single standard image resolution is needed. | `->scaleDown()` or `->fit()` |
| **`->disk($disk)`** | Route this specific upload to cloud storage (`s3`, `r2`, `spaces`) or private disk. | Unnecessary if storing to the default disk configured in `smart-upload.php`. | Set `'disk' => 's3'` globally in config |
| **`->replace($oldPath)`** | Auto-delete previous file from disk upon successful save (e.g. updating avatar via `DB::table()`). | **NEVER needed if your model uses `HasUploadPurge`!** (The trait auto-deletes old files upon `$user->update()`). | Use `HasUploadPurge` trait on Model |
| **`->temporary()`** | Stage upload in isolated temp storage during multi-step registration or SPA wizards. | Don't use for standard, immediate form submissions. | `Upload::temp($file)` one-liner |
| **`->save($directory)`** | **Terminal execution method.** Runs the optimized pipeline, verifies security, writes to disk, and arms transaction shields. | Must be called at the very end of the chain. | — |

---

## Static Facade Quick Reference

| Static Facade Method | Description | Return Type |
| :--- | :--- | :--- |
| `Upload::file($file)` | Start fluent builder with an uploaded file | `SmartUploader` |
| `Upload::files(array $files)` | Process multiple files as a collection | `UploadCollection` |
| `Upload::request($request, $key)` | Automatically handles single or array from request | `SmartUploader\|UploadCollection` |
| `Upload::temp($file)` | Instantly stage a file in temporary storage for two-step flows | `TempUploadResult` |
| `Upload::fromTemp($tempId)` | Promote a temporary upload to permanent storage | `SmartUploader` |
| `Upload::chunk($request)` | Handle incoming resumable chunk streams | `ChunkResult` |
| `Upload::url($path, $disk?)` | Format a stored relative path into a public URL | `string` |
| `Upload::temporaryUrl($path, $expiration, $disk?)` | Generate temporary signed cloud URL (S3/GCS/R2) | `string` |
| `Upload::download($path, $name?, $headers?, $disk?)` | Generate secure download HTTP response | `StreamedResponse` |
| `Upload::delete($path, $disk?)` | Safely delete a file and its responsive variants | `bool` |


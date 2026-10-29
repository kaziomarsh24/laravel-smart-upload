# Changelog

All notable changes to `thekaziomar/laravel-smart-upload` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-10-29

### Added
- **Zero-Boilerplate Request Input Helper:** Introduced `Upload::request($request, 'fieldName')` to automatically detect, validate, and upload single or multiple files directly from incoming HTTP requests without controller boilerplate.
- **Predictable Return Types by Intent:** `Upload::files(...)` now strictly guarantees returning `UploadCollection` regardless of file count (even for 1 file), eliminating undefined method issues when calling `->paths()`.
- **Polymorphic DTO Safety Nets:**
  - `UploadResult` now implements `paths(): array` and `urls(): array` (returning 1-element arrays for defensive caller compatibility).
  - `UploadCollection` now implements magic `__get()` and helper methods `path(): ?string` and `url(): ?string` (returning first item's path/url for seamless interoperability).
- **Array & JSON Column Auto-Purging (`HasUploadPurge`):**
  - Extended `safePurge()` to support `string|array|null` and raw JSON column values (e.g. `$casts = ['photos' => 'array']`).
  - Added smart array diffing on model updates: when removing photos from an array column, only the discarded files are purged from storage while remaining photos are preserved.
- Extended automated test suite to 50 test cases and 158 assertions (100% pass rate).

## [1.0.0] - 2026-10-29

### Added
- Fluent, order-independent builder syntax (`Upload::file()->toWebp()->save()`).
- Built-in Intervention Image v3 driver with auto Imagick/GD detection.
- Aspect ratio downscaling (`scaleDown()`) and exact center cropping (`fit()`).
- Resumable chunk upload engine with low-memory file streams (`Upload::chunk()`).
- Automatic Database Transaction Rollback protection (`TransactionShield`) with multi-connection and multi-tenant support.
- Eloquent model auto-cleanup trait (`HasUploadPurge`).
- SoftDeletes compatibility for model file auto-purging (only purging on `forceDelete`).
- Two-step temporary upload storage and promotion for mobile apps and SPAs.
- Strict security engine verifying magic-bytes, stripping EXIF, and blocking malicious scripts.
- Artisan command (`smart-upload:prune`) with automatic daily scheduling.
- Universal media URL formatting via `Upload::url()` and `upload_url()` helper (auto-resolving relative paths across Local, S3, R2, preserving external links, auto-prefixing directories, and fallback placeholders).
- Complete automated test suite covering 45 test cases and 131 assertions (100% pass rate).

### Security & Hardening Fixes
- Added `protected static $cached = false;` to `Upload` facade to eliminate cross-request builder state leaks in Laravel Octane and Queue Workers.
- Enforced strict `SecuritySanitizer` inspection on temporary file uploads (`Upload::temp()`).
- Restricted `fromTemp()` to the temporary storage directory with UUID boundary isolation (`^[0-9a-fA-F-]{36}$`).
- Added automatic post-promotion cleanup of temporary files (including when promoted by raw UUID) and remote `/tmp` buffers.
- Synchronized `disallowed_extensions` blacklist in `config/smart-upload.php` to include `.phar`, `.php7`, `.php8`, `.pht`, `.phps`, `.htaccess`, `.html`, and `.htm`. (Note: Existing publishers of config should re-publish or merge these extensions).
- Hardened SVG XSS inspection using generic `/\bon\w+\s*=/i` to catch all HTML event handlers.
- Implemented `encodeSafe()` and `getMimeForFormat()` in `ImageProcessor` to eliminate extension/content mismatches.
- Enhanced `TransactionShield::reset()` to disarm listener flags across repeated test suites.
- Added chunk upload bounds guard and DoS protection (`max_chunks` and `max_chunk_size`).

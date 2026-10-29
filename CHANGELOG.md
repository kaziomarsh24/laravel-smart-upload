# Changelog

All notable changes to `thekaziomar/laravel-smart-upload` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

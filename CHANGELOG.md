# Changelog

All notable changes to `thekaziomar/laravel-smart-upload` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.3] - 2026-10-29

### Security Hardening
- **Anti-Path Traversal & Null-Byte Defense on File Purges:** Explicitly guarded `Upload::delete()` and `HasUploadPurge::safePurge()` against path traversal sequences (`..`, `../`, `..\`) and null-byte injections (`\0`), preventing malicious path manipulation and Flysystem crashes.
- **Exception-Safe Filesystem Wrappers:** Guarded `Storage::disk()->url('')` and `Storage::disk()->delete()` calls inside defensive `try...catch (\Throwable)` blocks, ensuring cloud storage timeouts, unconfigured endpoints, or Flysystem errors gracefully return `false` without terminating `afterCommit` database callbacks.
- **Resilient Associative Array Purge Filtering:** `HasUploadPurge` now safely distinguishes associative dictionaries (e.g. `['label' => 'Logo']`) from sequential file lists, only extracting recognized file path keys (`path`, `url`, `file`) to prevent arbitrary metadata from triggering accidental root file deletions.
- **Configurable Purge Directory Whitelist with Path Normalization:** Introduced `purge_allowed_directories` to strictly constrain model file deletions to designated directories. Automatically normalizes relative paths, leading slashes (e.g. `/avatars`), and local storage URLs.
- **Allowed Extension Whitelist Overrides & Unbypassable PHP Guard:** Added `config('smart-upload.allowed_extensions')` support in `SecuritySanitizer`, allowing applications (like developer portals) to permit specific extensions (`.py`, `.sh`) while unbypassable server exploits (`php`, `phar`, `htaccess`) remain permanently blocked. Permitted extensions automatically bypass secondary MIME and double-extension blocks.

### Bug Fixes & Documentation
- **Explicit Plural Heuristics (`Upload::request`):** Replaced naive 's'-suffix checking with known plural array terms (`photos`, `images`, `files`, etc.) and array brackets (`[]`), avoiding false-positive array conversions on singular terms like `address`, `status`, or `process`.
- **Security Guidance & Markdown Fixes:** Documented security best practices for `$purgeableFiles` against mass-assignment manipulation, added storage visibility notes, and repaired orphan code fences in `README.md`.
- Automated test suite expanded to **60 tests and 186 assertions (100% pass rate)**.

## [1.0.2] - 2026-10-29

### Security Hardening
- **Host Verification on Deletions (`Upload::delete`):** Enforced strict host checking on HTTP/HTTPS URLs against the active storage disk and `app.url`, preventing attackers from triggering arbitrary local file deletions using foreign external URLs.
- **Hardcoded Baseline Config Union:** `SecuritySanitizer` now unconditionally unions its hardcoded baseline blacklist with `config('smart-upload.disallowed_extensions')`, guaranteeing full protection against `.phar`, `.php7`, `.php8`, and script extensions even when projects run older published configurations.
- **Unsafe Data URI Restriction:** `Upload::url()` now restricts data URIs strictly to `data:image/`, preventing injection of unsafe payloads (e.g. `data:text/html`).

### Bug Fixes & Improvements
- **Nested Array Model Purge Safety:** Fixed PHP `Array to string conversion` warnings in `HasUploadPurge` when updating columns containing nested associative arrays or object-like structures (e.g. `[['path' => '...', 'url' => '...']]`).
- **Strict Multi-Connection Transaction Isolation:** `TransactionShield` now tracks connection names individually for each guarded file. A rollback on a secondary connection (e.g. `tenant`) only purges files belonging to that connection, without prematurely deleting files guarded under the default or active parent connection.
- **Deferred Temp Promotion Cleanup:** Promoted temporary file cleanup (`fromTemp()->save()`) is now safely deferred to `DB::afterCommit()`, preventing temporary file loss if a database transaction rolls back.
- **Upload Request Error Transparency:** `Upload::request()` now throws descriptive `UploadException` instances containing the native upload error message (e.g. exceeding `upload_max_filesize`) instead of generic failures.
- **Documentation Overhaul:** Added Master Method Cheat Sheet & Decision Guide, cursor lazy-streaming mass deletion guide, and fixed orphan markdown fence formatting.
- Test suite expanded to **54 tests and 172 assertions (100% pass rate)**.

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

# Changelog

All notable changes to `thekaziomar/laravel-smart-upload` will be documented in this file.

## [1.0.0] - 2026-10-29

### Added
- Fluent, order-independent builder syntax (`Upload::file()->toWebp()->save()`).
- Built-in Intervention Image v3 driver with auto Imagick/GD detection.
- Aspect ratio downscaling (`scaleDown()`) and exact center cropping (`fit()`).
- Resumable chunk upload engine with low-memory file streams (`Upload::chunk()`).
- Automatic Database Transaction Rollback protection (Ghost file shield).
- Eloquent model auto-cleanup trait (`HasUploadPurge`).
- Two-step temporary upload storage and promotion for mobile apps and SPAs.
- Strict security engine verifying magic-bytes, stripping EXIF, and blocking malicious scripts.
- Artisan command (`smart-upload:prune`) with automatic daily scheduling.
- Complete automated test suite covering 27 test cases and 82 assertions.

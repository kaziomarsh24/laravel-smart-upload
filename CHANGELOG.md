# Changelog

All notable changes to `thekaziomar/laravel-smart-upload` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased] - (Pre-release v1.0.0)

### Added
- Fluent, order-independent builder syntax (`Upload::file()->toWebp()->save()`).
- Built-in Intervention Image v3 driver with auto Imagick/GD detection.
- Aspect ratio downscaling (`scaleDown()`) and exact center cropping (`fit()`).
- Resumable chunk upload engine with low-memory file streams (`Upload::chunk()`).
- Automatic Database Transaction Rollback protection (Ghost file shield).
- Eloquent model auto-cleanup trait (`HasUploadPurge`).
- SoftDeletes compatibility for model file auto-purging (only purging on `forceDelete`).
- Two-step temporary upload storage and promotion for mobile apps and SPAs.
- Strict security engine verifying magic-bytes, stripping EXIF, and blocking malicious scripts.
- Artisan command (`smart-upload:prune`) with automatic daily scheduling.
- Complete automated test suite covering 34 test cases and 99 assertions (100% pass rate).

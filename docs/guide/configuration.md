# Configuration Reference

Publish the configuration file using Artisan:

```bash
php artisan vendor:publish --tag="smart-upload-config"
```

The configuration is saved to `config/smart-upload.php`. Each option is explained below:

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    | The default storage disk used when ->disk() is not chained.
    | Fallbacks to config('filesystems.default') if undefined.
    */
    'disk' => env('SMART_UPLOAD_DISK', env('FILESYSTEM_DISK', 'public')),

    /*
    |--------------------------------------------------------------------------
    | Automatic WebP Conversion
    |--------------------------------------------------------------------------
    | Automatically converts uploaded images into high-performance WebP.
    | Set to false to retain input image formats globally.
    */
    'auto_webp' => env('SMART_UPLOAD_AUTO_WEBP', true),

    /*
    |--------------------------------------------------------------------------
    | Image Compression Quality
    |--------------------------------------------------------------------------
    | Compression quality from 1 (lowest size) to 100 (highest quality).
    */
    'quality' => (int) env('SMART_UPLOAD_QUALITY', 85),

    /*
    |--------------------------------------------------------------------------
    | Maximum Image Dimensions
    |--------------------------------------------------------------------------
    | Global thresholds for scaleDown(). Images larger than these boundaries
    | are downscaled while maintaining original aspect ratios.
    */
    'max_width' => env('SMART_UPLOAD_MAX_WIDTH', 1920),
    'max_height' => env('SMART_UPLOAD_MAX_HEIGHT', 1080),

    /*
    |--------------------------------------------------------------------------
    | Database Transaction Protection
    |--------------------------------------------------------------------------
    | When true, uploads occurring within DB::transaction() register rollback
    | listeners. If an unhandled exception occurs, uploaded files are purged.
    */
    'transaction_safe' => env('SMART_UPLOAD_TRANSACTION_SAFE', true),

    /*
    |--------------------------------------------------------------------------
    | Temporary Storage & Chunk Settings
    |--------------------------------------------------------------------------
    | Configurations for two-step SPA uploads and large resumable chunk streams.
    */
    'temporary' => [
        'disk' => env('SMART_UPLOAD_TEMP_DISK', 'local'),
        'directory' => env('SMART_UPLOAD_TEMP_DIR', 'smart-upload/temp'),
        'chunk_directory' => env('SMART_UPLOAD_CHUNK_DIR', 'smart-upload/chunks'),
        'expiration_hours' => (int) env('SMART_UPLOAD_TEMP_EXPIRES', 24),
        'max_chunks' => (int) env('SMART_UPLOAD_MAX_CHUNKS', 10000),
        'max_chunk_size' => (int) env('SMART_UPLOAD_MAX_CHUNK_SIZE', 52428800), // 50MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Schedule Maintenance
    |--------------------------------------------------------------------------
    | Automatically schedules the `smart-upload:prune` maintenance command.
    */
    'auto_schedule_prune' => env('SMART_UPLOAD_AUTO_SCHEDULE_PRUNE', true),

    /*
    |--------------------------------------------------------------------------
    | Disallowed Script Extensions (Blacklist)
    |--------------------------------------------------------------------------
    | Extensions permanently rejected by SecuritySanitizer.
    */
    'disallowed_extensions' => [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phar', 'phps',
        'phtml', 'exe', 'sh', 'bat', 'cgi', 'pl', 'py', 'jar', 'jsp', 'asp',
        'aspx', 'vbs', 'com', 'htaccess', 'html', 'htm'
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Extensions (Whitelist Override)
    |--------------------------------------------------------------------------
    | Permitted extensions for developer platforms (e.g. ['py', 'sh']).
    | Note: Server-side exploits (php, phar, htaccess) can never be whitelisted.
    */
    'allowed_extensions' => [],

    /*
    |--------------------------------------------------------------------------
    | Recognized Responsive Variant Suffixes
    |--------------------------------------------------------------------------
    | Suffixes recognized during sibling file cleanup and replacement.
    */
    'variant_names' => [
        'thumb', 'thumbnail', 'mobile', 'tablet', 'desktop', 'hd', 'small', 'medium', 'large'
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Purge Allowed Directories
    |--------------------------------------------------------------------------
    | Constrains HasUploadPurge model cleanup to designated directories.
    | Leave empty to permit deletions across all directories.
    */
    'purge_allowed_directories' => [],

    /*
    |--------------------------------------------------------------------------
    | Named Presets
    |--------------------------------------------------------------------------
    | Reusable presets invoked via ->preset('name').
    */
    'presets' => [
        'avatar' => [
            'fit' => [300, 300],
            'webp' => true,
            'quality' => 85,
        ],
        'thumbnail' => [
            'fit' => [150, 150],
            'webp' => true,
            'quality' => 80,
        ],
        'banner' => [
            'scale_down' => [1920, 600],
            'webp' => true,
            'quality' => 85,
        ],
    ],
];
```

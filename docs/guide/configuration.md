# Configuration Reference

The full configuration file is published to `config/smart-upload.php`:

```php
return [
    // Default filesystem disk (defaults to 'public')
    'disk' => env('SMART_UPLOAD_DISK', env('FILESYSTEM_DISK', 'public')),

    // Auto-convert uploaded images to modern WebP format
    'auto_webp' => env('SMART_UPLOAD_AUTO_WEBP', true),

    // Default image compression quality (1-100)
    'quality' => (int) env('SMART_UPLOAD_QUALITY', 85),

    // Max image dimensions for automatic scaleDown()
    'max_width' => env('SMART_UPLOAD_MAX_WIDTH', 1920),
    'max_height' => env('SMART_UPLOAD_MAX_HEIGHT', 1080),

    // Automatically purge files if DB transaction rolls back
    'transaction_safe' => env('SMART_UPLOAD_TRANSACTION_SAFE', true),

    // Temporary storage settings
    'temporary' => [
        'disk' => env('SMART_UPLOAD_TEMP_DISK', 'local'),
        'directory' => env('SMART_UPLOAD_TEMP_DIR', 'smart-upload/temp'),
        'chunk_directory' => env('SMART_UPLOAD_CHUNK_DIR', 'smart-upload/chunks'),
        'expiration_hours' => (int) env('SMART_UPLOAD_TEMP_EXPIRES', 24),
    ],

    // Named presets for standardized dimensions
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


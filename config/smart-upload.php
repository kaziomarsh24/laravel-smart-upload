<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Storage Disk
    |--------------------------------------------------------------------------
    | The default filesystem disk where uploaded files will be stored.
    | Fallbacks to Laravel's default filesystem disk if not defined.
    */
    'disk' => env('SMART_UPLOAD_DISK', env('FILESYSTEM_DISK', 'public')),

    /*
    |--------------------------------------------------------------------------
    | Automatic WebP Conversion
    |--------------------------------------------------------------------------
    | When enabled, all uploaded images will automatically be converted to WebP
    | format unless explicitly requested otherwise via ->keepOriginalFormat().
    */
    'auto_webp' => env('SMART_UPLOAD_AUTO_WEBP', true),

    /*
    |--------------------------------------------------------------------------
    | Default Image Compression Quality
    |--------------------------------------------------------------------------
    | Defines the default quality (1-100) for encoded WebP/JPEG images.
    */
    'quality' => (int) env('SMART_UPLOAD_QUALITY', 85),

    /*
    |--------------------------------------------------------------------------
    | Max Image Dimensions (Scale Down)
    |--------------------------------------------------------------------------
    | Defines the maximum default width and height for images. When defined,
    | images exceeding these dimensions are downscaled keeping aspect ratio.
    */
    'max_width' => env('SMART_UPLOAD_MAX_WIDTH', 1920),
    'max_height' => env('SMART_UPLOAD_MAX_HEIGHT', 1080),

    /*
    |--------------------------------------------------------------------------
    | Database Transaction Auto-Shield (Atomic File Safety)
    |--------------------------------------------------------------------------
    | When true, if an upload occurs inside a DB::transaction() and the transaction
    | fails or rolls back, the uploaded file on disk/S3 is automatically deleted.
    */
    'transaction_safe' => env('SMART_UPLOAD_TRANSACTION_SAFE', true),

    /*
    |--------------------------------------------------------------------------
    | Temporary / Chunk Storage Settings
    |--------------------------------------------------------------------------
    | Disk and folder paths used for temporary mobile uploads and chunks.
    */
    'temporary' => [
        'disk' => env('SMART_UPLOAD_TEMP_DISK', 'local'),
        'directory' => env('SMART_UPLOAD_TEMP_DIR', 'smart-upload/temp'),
        'chunk_directory' => env('SMART_UPLOAD_CHUNK_DIR', 'smart-upload/chunks'),
        'expiration_hours' => (int) env('SMART_UPLOAD_TEMP_EXPIRES', 24),
        'max_chunks' => (int) env('SMART_UPLOAD_MAX_CHUNKS', 10000),
        'max_chunk_size' => (int) env('SMART_UPLOAD_MAX_CHUNK_SIZE', 52428800), // 50MB per chunk
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Schedule Pruning
    |--------------------------------------------------------------------------
    | Automatically registers `smart-upload:prune` to Laravel's scheduler.
    */
    'auto_schedule_prune' => env('SMART_UPLOAD_AUTO_SCHEDULE_PRUNE', true),

    /*
    |--------------------------------------------------------------------------
    | Security: Disallowed Script Extensions
    |--------------------------------------------------------------------------
    | Explicit blacklist of executable script extensions that will be rejected.
    */
    'disallowed_extensions' => [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phar', 'phps',
        'phtml', 'exe', 'sh', 'bat', 'cgi', 'pl', 'py', 'jar', 'jsp', 'asp',
        'aspx', 'vbs', 'com', 'htaccess', 'html', 'htm'
    ],

    /*
    |--------------------------------------------------------------------------
    | Security: Explicitly Allowed Extensions (Whitelist Override)
    |--------------------------------------------------------------------------
    | Useful for developer portals or specific projects where scripts (.py, .sh)
    | are intentionally permitted. Takes precedence over disallowed_extensions.
    |
    | WARNING: Critical server-side executable extensions (php, phar, htaccess)
    | are permanently forbidden by Smart Upload and cannot be bypassed.
    */
    'allowed_extensions' => [],

    /*
    |--------------------------------------------------------------------------
    | Security: Purge Allowed Directories
    |--------------------------------------------------------------------------
    | Restricts automatic model file purging to specific directories
    | (e.g. ['avatars', 'photos', 'uploads']). Values are automatically normalized
    | (accepting relative paths, leading slashes '/avatars', or full URLs).
    | Leave empty to allow all storage paths.
    */
    'purge_allowed_directories' => [],

    /*
    |--------------------------------------------------------------------------
    | Named Presets
    |--------------------------------------------------------------------------
    | Common image processing presets you can invoke via ->preset('name').
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
        'content' => [
            'scale_down' => [1200, 800],
            'webp' => true,
            'quality' => 85,
        ],
    ],
];

<?php

namespace KaziOmar\SmartUpload\Processors;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

/**
 * Class ImageProcessor
 *
 * Handles image manipulation (scaleDown, fit/cover, WebP conversion, auto-orientation)
 * using Intervention Image v3 with automatic GD/Imagick driver detection and safe format fallbacks.
 */
class ImageProcessor
{
    protected ImageManager $manager;

    public function __construct(?ImageManager $manager = null)
    {
        if ($manager !== null) {
            $this->manager = $manager;
            return;
        }

        // Auto-select Imagick if available, otherwise fallback to GD
        $driver = extension_loaded('imagick') && class_exists(ImagickDriver::class)
            ? new ImagickDriver()
            : new GdDriver();

        $this->manager = new ImageManager($driver);
    }

    /**
     * Check if an uploaded file is a manipulable raster image.
     */
    public function isImage(UploadedFile $file): bool
    {
        $mime = $file->getMimeType() ?: '';

        // Note: SVGs are vector XMLs, not raster images for GD/Imagick manipulation
        return str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml';
    }

    /**
     * Read an image from file.
     */
    public function read(UploadedFile|string $source): ImageInterface
    {
        $path = is_string($source) ? $source : $source->getPathname();
        return $this->manager->read($path);
    }

    /**
     * Scale down an image preserving aspect ratio without enlarging smaller images.
     */
    public function scaleDown(ImageInterface $image, ?int $maxWidth = null, ?int $maxHeight = null): ImageInterface
    {
        if ($maxWidth === null && $maxHeight === null) {
            return $image;
        }

        return $image->scaleDown(width: $maxWidth, height: $maxHeight);
    }

    /**
     * Crop & fit an image to exact dimensions centered (smart cover).
     */
    public function fit(ImageInterface $image, int $width, int $height): ImageInterface
    {
        return $image->cover($width, $height);
    }

    /**
     * Safely encode an image to the requested format, returning both the encoded binary
     * and the actual confirmed format extension.
     *
     * @return array{0: string, 1: string} [encodedString, actualFormat]
     */
    public function encodeSafe(ImageInterface $image, string $format, int $quality = 85): array
    {
        $target = strtolower($format);

        if ($target === 'jpg') {
            $target = 'jpeg';
        }

        try {
            return match ($target) {
                'webp' => [(string) $image->toWebp($quality), 'webp'],
                'jpeg' => [(string) $image->toJpeg($quality), 'jpeg'],
                'png' => [(string) $image->toPng(), 'png'],
                'gif' => [(string) $image->toGif(), 'gif'],
                'avif' => function_exists('imageavif') ? [(string) $image->toAvif($quality), 'avif'] : [(string) $image->toWebp($quality), 'webp'],
                'bmp' => method_exists($image, 'toBitmap') ? [(string) $image->toBitmap(), 'bmp'] : [(string) $image->toPng(), 'png'],
                'tiff', 'tif' => method_exists($image, 'toTiff') ? [(string) $image->toTiff(), 'tiff'] : [(string) $image->toJpeg($quality), 'jpeg'],
                default => [(string) $image->toWebp($quality), 'webp'],
            };
        } catch (Throwable) {
            // Driver fallback to WebP or PNG if specific encoder failed
            try {
                return [(string) $image->toWebp($quality), 'webp'];
            } catch (Throwable) {
                return [(string) $image->toPng(), 'png'];
            }
        }
    }

    /**
     * Standard encode method (backward-compatible).
     */
    public function encode(ImageInterface $image, string $format, int $quality = 85): string
    {
        [$encoded] = $this->encodeSafe($image, $format, $quality);
        return $encoded;
    }

    /**
     * Get the standardized MIME type for a given format extension.
     */
    public function getMimeForFormat(string $format): string
    {
        return match (strtolower($format)) {
            'jpeg', 'jpg' => 'image/jpeg',
            'webp' => 'image/webp',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'avif' => 'image/avif',
            'bmp' => 'image/bmp',
            'tiff', 'tif' => 'image/tiff',
            default => 'image/' . strtolower($format),
        };
    }
}

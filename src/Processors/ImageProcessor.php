<?php

namespace KaziOmar\SmartUpload\Processors;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Class ImageProcessor
 *
 * Handles image manipulation (scaleDown, fit/cover, WebP conversion, auto-orientation)
 * using Intervention Image v3 with automatic GD/Imagick driver detection.
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
     * Check if an uploaded file is a manipulable image.
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
     * Scale down an image so width/height do not exceed max constraints,
     * maintaining aspect ratio and never upsizing smaller images.
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
     * Encode the image to the specified format with given quality.
     */
    public function encode(ImageInterface $image, string $format, int $quality = 85): string
    {
        $normalizedFormat = strtolower($format);

        if ($normalizedFormat === 'jpg') {
            $normalizedFormat = 'jpeg';
        }

        $encoded = match ($normalizedFormat) {
            'webp' => $image->toWebp($quality),
            'jpeg' => $image->toJpeg($quality),
            'png' => $image->toPng(),
            'gif' => $image->toGif(),
            'avif' => function_exists('imageavif') ? $image->toAvif($quality) : $image->toWebp($quality),
            'bmp' => method_exists($image, 'toBitmap') ? $image->toBitmap() : $image->toPng(),
            default => $image->toWebp($quality),
        };

        return (string) $encoded;
    }
}

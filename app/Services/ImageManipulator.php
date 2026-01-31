<?php

namespace App\Services;

use Spatie\Image\Enums\AlignPosition;
use Spatie\Image\Enums\CropPosition;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Enums\FlipDirection;
use Spatie\Image\Image;
use Spatie\Image\Manipulations;

class ImageManipulator extends Manipulations
{
    /**
     * Strip EXIF metadata and convert to sRGB color space
     */
    public function apply(Image $image): Image
    {
        // Get underlying Imagick driver
        $imagick = null;

        // Try to get Imagick instance from Spatie Image
        try {
            // Spatie Image v3 uses drivers, get the core Imagick object
            $driver = $image->getDriver();

            if ($driver && method_exists($driver, 'getCore')) {
                $imagick = $driver->getCore();
            }
        } catch (\Exception $e) {
            // If we can't get Imagick, just return original image
            return $image;
        }

        // Apply EXIF strip and sRGB conversion if Imagick is available
        if ($imagick instanceof \Imagick) {
            // Convert color space to sRGB
            $imagick->transformImageColorspace(\Imagick::COLORSPACE_SRGB);

            // Strip all EXIF metadata
            $imagick->stripImage();
        }

        return $image;
    }
}

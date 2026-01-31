<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Image Processing Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default image processing driver.
    |
    | Supported: "imagick", "vips"
    | - imagick: ImageMagick (legacy, higher memory usage)
    | - vips: libvips (4-8x faster, 10x less memory) - RECOMMENDED
    |
    */

    'driver' => env('IMAGE_DRIVER', 'vips'),

    /*
    |--------------------------------------------------------------------------
    | Fallback Driver
    |--------------------------------------------------------------------------
    |
    | If the primary driver fails, this driver will be used as fallback.
    |
    */

    'fallback_driver' => env('IMAGE_FALLBACK_DRIVER', 'imagick'),

    /*
    |--------------------------------------------------------------------------
    | Conversion Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for image conversion operations.
    |
    */

    'conversion' => [
        // Maximum dimension (width or height) for converted images
        'max_dimension' => env('IMAGE_MAX_DIMENSION', 3000),

        // JPEG quality (1-100)
        'quality' => env('IMAGE_QUALITY', 90),

        // Target color space (sRGB recommended for web)
        'colorspace' => 'srgb',

        // Strip metadata (EXIF, GPS, etc.) for privacy
        'strip_metadata' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Thumbnail Settings
    |--------------------------------------------------------------------------
    |
    | Settings for thumbnail generation.
    |
    */

    'thumbnails' => [
        'thumb' => [
            'width' => 300,
            'height' => 300,
        ],
        'preview' => [
            'width' => 1200,
            'height' => 1200,
        ],
    ],
];

<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;

class WatermarkService
{
    /**
     * Apply tiled watermark text across the entire image.
     *
     * Uniform grid at -30 degrees, white semi-transparent text.
     * No face-skipping, no random sizes, no black color.
     */
    public function applyTiledWatermark(string $imagePath, string $text): void
    {
        try {
            $manager = new ImageManager(new \Intervention\Image\Drivers\Imagick\Driver());
            $image = $manager->read($imagePath);

            $width = $image->width();
            $height = $image->height();

            // Responsive font size – subtle, not overwhelming
            $fontSize = (int) max(16, min($width, $height) / 25);
            $fontPath = $this->resolveFontPath();

            // Spacing between watermark cells – sparse enough to see the photo
            $colSpacing = $fontSize * 10;
            $rowSpacing = (int) ($fontSize * 6);

            // Because text is rotated -30 degrees, we need extra coverage
            // beyond image boundaries so corners are not left empty.
            $diagonal = (int) sqrt($width * $width + $height * $height);
            $padX = (int) (($diagonal - $width) / 2) + $colSpacing;
            $padY = (int) (($diagonal - $height) / 2) + $rowSpacing;

            // Center of the image (rotation pivot)
            $cx = $width / 2;
            $cy = $height / 2;
            $angleRad = deg2rad(-30);
            $cosA = cos($angleRad);
            $sinA = sin($angleRad);

            // Place text in a grid covering the extended area,
            // then rotate each cell position around the image center.
            for ($gy = -$padY; $gy < $height + $padY; $gy += $rowSpacing) {
                for ($gx = -$padX; $gx < $width + $padX; $gx += $colSpacing) {
                    // Translate to origin, rotate, translate back
                    $dx = $gx - $cx;
                    $dy = $gy - $cy;
                    $rx = (int) ($cosA * $dx - $sinA * $dy + $cx);
                    $ry = (int) ($sinA * $dx + $cosA * $dy + $cy);

                    // Skip if the rotated point is far outside the image
                    if ($rx < -$colSpacing || $rx > $width + $colSpacing
                        || $ry < -$rowSpacing || $ry > $height + $rowSpacing) {
                        continue;
                    }

                    $image->text($text, $rx, $ry, function ($font) use ($fontSize, $fontPath) {
                        $font->file($fontPath);
                        $font->size($fontSize);
                        $font->color('rgba(255, 255, 255, 0.15)');
                        $font->align('center');
                        $font->valign('middle');
                        $font->angle(-30);
                    });
                }
            }

            $image->save($imagePath, quality: 90);

            Log::info('Tiled watermark applied', [
                'image_path' => $imagePath,
                'text' => $text,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to apply tiled watermark', [
                'image_path' => $imagePath,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function resolveFontPath(): string
    {
        $candidates = [
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'),
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        throw new \RuntimeException('DejaVuSans-Bold.ttf font not found');
    }
}

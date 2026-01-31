<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;

class WatermarkService
{
    /**
     * Add circular watermark to an image
     *
     * @param  string  $imagePath  Path to the image file
     * @param  string  $watermarkText  Text to use as watermark
     * @return void
     */
    public function addCircularWatermark(string $imagePath, string $watermarkText): void
    {
        try {
            // Use Imagick driver for watermarking
            $manager = new ImageManager(new \Intervention\Image\Drivers\Imagick\Driver());

            // Read the image
            $image = $manager->read($imagePath);

            // Get image dimensions
            $width = $image->width();
            $height = $image->height();

            // Calculate font size based on image dimensions (responsive)
            $baseFontSize = max(24, min($width, $height) / 25);

            // Font path for Alpine Linux
            $fontPath = '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf';

            // Add moderate grid pattern of watermarks - avoiding face area
            $horizontalSpacing = $width / 5;  // Kevesebb vízjel
            $verticalSpacing = $height / 6;

            // Define face area (center-top region) to avoid - LARGER area
            $faceCenterX = $width / 2;
            $faceCenterY = $height / 3; // Face is typically in upper third
            $faceRadius = min($width, $height) * 0.35; // Face area radius - NAGYOBB (volt: 0.25)

            for ($row = 0; $row <= 6; $row++) {
                for ($col = 0; $col <= 5; $col++) {
                    $x = $col * $horizontalSpacing;
                    $y = $row * $verticalSpacing;

                    // Check if position is in face area - skip if too close
                    $distanceFromFace = sqrt(pow($x - $faceCenterX, 2) + pow($y - $faceCenterY, 2));
                    if ($distanceFromFace < $faceRadius) {
                        continue; // Skip face area
                    }

                    // Random size variation (70% - 100%)
                    $sizeVariation = 0.7 + (($row + $col) % 4) * 0.075;
                    $fontSize = (int) ($baseFontSize * $sizeVariation);

                    // Random angle variation for more natural look
                    $angles = [-30, 0, 30];
                    $angleIndex = abs(($row * 6 + $col)) % 3;
                    $angle = $angles[$angleIndex];

                    // Váltakozó fehér/fekete vízjelek - minden második fehér, minden második fekete
                    $isWhite = (($row + $col) % 2) == 0;
                    
                    if ($isWhite) {
                        // Fehér vízjel
                        $image->text($watermarkText, (int) $x, (int) $y, function ($font) use ($fontSize, $angle, $fontPath) {
                            $font->file($fontPath);
                            $font->size($fontSize);
                            $font->color('rgba(255, 255, 255, 0.30)'); // Fehér 30% opacity
                            $font->align('center');
                            $font->valign('middle');
                            $font->angle($angle);
                        });
                    } else {
                        // Fekete vízjel
                        $image->text($watermarkText, (int) $x, (int) $y, function ($font) use ($fontSize, $angle, $fontPath) {
                            $font->file($fontPath);
                            $font->size($fontSize);
                            $font->color('rgba(0, 0, 0, 0.30)'); // Fekete 30% opacity
                            $font->align('center');
                            $font->valign('middle');
                            $font->angle($angle);
                        });
                    }
                }
            }

            // Add moderate diagonal watermarks - avoiding face area
            $diagonalSpacing = min($width, $height) / 6;

            for ($i = -6; $i <= 6; $i++) {
                // Top-left to bottom-right diagonal
                $x1 = ($width / 2) + ($i * $diagonalSpacing);
                $y1 = ($height / 2) + ($i * $diagonalSpacing);

                // Check if position is in face area - skip if too close
                $distanceFromFace = sqrt(pow($x1 - $faceCenterX, 2) + pow($y1 - $faceCenterY, 2));
                if ($distanceFromFace < $faceRadius) {
                    continue; // Skip face area
                }

                $fontSize = (int) ($baseFontSize * (0.6 + abs($i % 3) * 0.1));

                // Váltakozó fehér/fekete diagonális vízjelek
                $isWhite = (abs($i) % 2) == 0;

                if ($isWhite) {
                    // Fehér diagonális vízjel
                    $image->text($watermarkText, (int) $x1, (int) $y1, function ($font) use ($fontSize, $fontPath) {
                        $font->file($fontPath);
                        $font->size($fontSize);
                        $font->color('rgba(255, 255, 255, 0.25)'); // Fehér 25% opacity
                        $font->align('center');
                        $font->valign('middle');
                        $font->angle(-45);
                    });
                } else {
                    // Fekete diagonális vízjel
                    $image->text($watermarkText, (int) $x1, (int) $y1, function ($font) use ($fontSize, $fontPath) {
                        $font->file($fontPath);
                        $font->size($fontSize);
                        $font->color('rgba(0, 0, 0, 0.25)'); // Fekete 25% opacity
                        $font->align('center');
                        $font->valign('middle');
                        $font->angle(-45);
                    });
                }

                // Top-right to bottom-left diagonal
                $x2 = ($width / 2) + ($i * $diagonalSpacing);
                $y2 = ($height / 2) - ($i * $diagonalSpacing);

                // Check if position is in face area - skip if too close
                $distanceFromFace2 = sqrt(pow($x2 - $faceCenterX, 2) + pow($y2 - $faceCenterY, 2));
                if ($distanceFromFace2 < $faceRadius) {
                    continue; // Skip face area
                }

                // Váltakozó fehér/fekete második diagonális vízjelek
                $isWhite2 = (abs($i) % 2) == 1; // Fordított váltakozás

                if ($isWhite2) {
                    // Fehér második diagonális vízjel
                    $image->text($watermarkText, (int) $x2, (int) $y2, function ($font) use ($fontSize, $fontPath) {
                        $font->file($fontPath);
                        $font->size($fontSize);
                        $font->color('rgba(255, 255, 255, 0.25)'); // Fehér 25% opacity
                        $font->align('center');
                        $font->valign('middle');
                        $font->angle(45);
                    });
                } else {
                    // Fekete második diagonális vízjel
                    $image->text($watermarkText, (int) $x2, (int) $y2, function ($font) use ($fontSize, $fontPath) {
                        $font->file($fontPath);
                        $font->size($fontSize);
                        $font->color('rgba(0, 0, 0, 0.25)'); // Fekete 25% opacity
                        $font->align('center');
                        $font->valign('middle');
                        $font->angle(45);
                    });
                }
            }

            // Save the image back to the same path
            $image->save($imagePath);

            Log::info('Watermark applied successfully', [
                'image_path' => $imagePath,
                'watermark_text' => $watermarkText,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to apply watermark', [
                'image_path' => $imagePath,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DEV ONLY: Ha egy kep nem talalhato a storage-ban,
 * visszaad egy placeholder kepet a 404 helyett.
 *
 * Csak APP_ENV=local eseten aktiv.
 */
class DevPlaceholderImage
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Csak local env-ben aktiv
        if (! app()->environment('local') || $response->getStatusCode() !== 404) {
            return $response;
        }

        $path = $request->path();
        if (! preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $path)) {
            return $response;
        }

        $width = min(max((int) $request->query('w', 400), 50), 2000);
        $height = min(max((int) $request->query('h', 300), 50), 2000);

        $img = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($img, 229, 231, 235);
        $textColor = imagecolorallocate($img, 107, 114, 128);
        imagefill($img, 0, 0, $bg);

        $text = "{$width}x{$height}";
        $fontSize = 4;
        $textWidth = imagefontwidth($fontSize) * strlen($text);
        $textHeight = imagefontheight($fontSize);
        imagestring(
            $img,
            $fontSize,
            (int) (($width - $textWidth) / 2),
            (int) (($height - $textHeight) / 2),
            $text,
            $textColor
        );

        ob_start();
        imagepng($img);
        $content = ob_get_clean();
        imagedestroy($img);

        return response($content, 200)
            ->header('Content-Type', 'image/png')
            ->header('X-Placeholder', 'true');
    }
}

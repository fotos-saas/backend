<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;

/**
 * Közös fájl validációs trait.
 *
 * Redundáns kód elkerülése érdekében a ZIP és kép validációs
 * metódusok egy helyen vannak definiálva.
 */
trait FileValidation
{
    /**
     * ZIP fájl-e ellenőrzés.
     *
     * MIME type és kiterjesztés alapján ellenőrzi, hogy a fájl ZIP-e.
     */
    public function isZipFile(UploadedFile $file): bool
    {
        $mimeType = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        return in_array($mimeType, ['application/zip', 'application/x-zip-compressed'])
            || $extension === 'zip';
    }

    /**
     * Valódi képfájl ellenőrzés.
     *
     * Kiterjesztés, MIME type és getimagesize() alapján ellenőrzi.
     *
     * @param UploadedFile $file A feltöltött fájl
     * @param array $supportedExtensions Támogatott kiterjesztések (alapértelmezett: jpg, jpeg, png, webp)
     * @param array $supportedMimes Támogatott MIME típusok (alapértelmezett: image/jpeg, image/png, image/webp)
     * @param bool $useGetImageSize getimagesize() ellenőrzés használata (alapértelmezett: true)
     */
    public function isValidImageFile(
        UploadedFile $file,
        array $supportedExtensions = ['jpg', 'jpeg', 'png', 'webp'],
        array $supportedMimes = ['image/jpeg', 'image/png', 'image/webp'],
        bool $useGetImageSize = true
    ): bool {
        // Kiterjesztés ellenőrzés
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $supportedExtensions)) {
            return false;
        }

        // MIME type ellenőrzés
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $supportedMimes)) {
            return false;
        }

        // getimagesize() ellenőrzés (opcionális, de ajánlott)
        if ($useGetImageSize) {
            $imageInfo = @getimagesize($file->getPathname());
            if ($imageInfo === false) {
                return false;
            }

            // Szélesség és magasság ellenőrzés
            if ($imageInfo[0] <= 0 || $imageInfo[1] <= 0) {
                return false;
            }
        }

        return true;
    }
}

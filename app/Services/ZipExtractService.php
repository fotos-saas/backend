<?php

namespace App\Services;

use App\Models\ConversionJob;
use App\Models\ConversionMedia;
use App\Traits\FileValidation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class ZipExtractService
{
    use FileValidation;
    /**
     * Támogatott képformátumok kiterjesztései
     */
    protected array $supportedExtensions = [
        'heic', 'heif', 'webp', 'avif', 'jxl',
        'dng', 'cr2', 'nef', 'arw', 'orf', 'rw2',
        'jpeg', 'jpg', 'png', 'bmp',
    ];

    /**
     * Kihagyandó fájlok és mappák
     */
    protected array $hiddenPatterns = [
        '__MACOSX',
        '.DS_Store',
        '.git',
        '.svn',
        'Thumbs.db',
        'desktop.ini',
    ];

    /**
     * Maximum kicsomagolt méret (500MB)
     */
    protected int $maxExtractedSize = 524288000;

    public function __construct(
        protected ImageConversionService $conversionService
    ) {}

    /**
     * ZIP fájl kicsomagolása és képek tárolása
     *
     * @return array Feltöltött média rekordok listája
     */
    public function extractAndStore(UploadedFile $zipFile, ConversionJob $job, bool $skipConversions = true): array
    {
        $zip = new ZipArchive();
        $result = $zip->open($zipFile->getRealPath());

        if ($result !== true) {
            throw new \RuntimeException('Nem sikerült megnyitni a ZIP fájlt. Hibakód: ' . $result);
        }

        // ZIP fájl neve (kiterjesztés nélkül) - ez lesz a gyökér mappa
        // NFC normalizálás az ékezetes karakterekhez (macOS NFD → NFC)
        $zipBaseName = pathinfo($zipFile->getClientOriginalName(), PATHINFO_FILENAME);
        if (class_exists('Normalizer')) {
            $zipBaseName = \Normalizer::normalize($zipBaseName, \Normalizer::FORM_C);
        }

        $uploadedMedia = [];
        $totalExtractedSize = 0;

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $entryName = $stat['name'];

                // Security: path traversal ellenőrzés
                if ($this->isPathTraversal($entryName)) {
                    Log::warning('ZIP path traversal kísérlet', ['entry' => $entryName]);
                    continue;
                }

                // Hidden fájlok és mappák kihagyása
                if ($this->isHiddenEntry($entryName)) {
                    continue;
                }

                // Csak fájlok (nem mappák)
                if (str_ends_with($entryName, '/')) {
                    continue;
                }

                // Csak képfájlok
                if (! $this->isImageFile($entryName)) {
                    continue;
                }

                // ZIP bomb védelem
                $totalExtractedSize += $stat['size'];
                if ($totalExtractedSize > $this->maxExtractedSize) {
                    throw new \RuntimeException('A ZIP fájl túl nagy (max 500MB kicsomagolva)');
                }

                // Fájl tartalom kinyerése
                $content = $zip->getFromIndex($i);
                if ($content === false) {
                    Log::warning('Nem sikerült kinyerni a fájlt a ZIP-ből', ['entry' => $entryName]);
                    continue;
                }

                // Temp fájl létrehozása
                $tempPath = sys_get_temp_dir() . '/' . uniqid('zip_extract_') . '_' . basename($entryName);
                file_put_contents($tempPath, $content);

                try {
                    // UploadedFile létrehozása a temp fájlból
                    $uploadedFile = new UploadedFile(
                        $tempPath,
                        basename($entryName),
                        mime_content_type($tempPath),
                        null,
                        true // test mode - nem ellenőrzi az upload flag-et
                    );

                    // Folder path kinyerése az entry névből (ZIP neve + belső path)
                    $folderPath = $this->extractFolderPath($entryName, $zipBaseName);

                    // Kép tárolása a meglévő service-szel
                    $media = $this->conversionService->storeImage(
                        $job,
                        $uploadedFile,
                        $folderPath,
                        $skipConversions
                    );

                    $uploadedMedia[] = [
                        'id' => $media->id,
                        'original_name' => $media->getOriginalFilename(),
                        'folder_path' => $media->folder_path,
                        'phase' => $media->getPhase(),
                        'thumb_url' => null,
                        'preview_url' => null,
                    ];
                } finally {
                    // Temp fájl törlése
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                    }
                }
            }
        } finally {
            $zip->close();
        }

        return $uploadedMedia;
    }

    /**
     * Path traversal támadás ellenőrzése
     */
    protected function isPathTraversal(string $entryName): bool
    {
        // Normalizálás
        $normalized = str_replace('\\', '/', $entryName);

        // ../ vagy kezdő / ellenőrzés
        if (str_contains($normalized, '../') || str_starts_with($normalized, '/')) {
            return true;
        }

        // Abszolút Windows útvonal (C:\ stb.)
        if (preg_match('/^[a-zA-Z]:/', $normalized)) {
            return true;
        }

        return false;
    }

    /**
     * Hidden fájl vagy mappa ellenőrzése
     */
    protected function isHiddenEntry(string $entryName): bool
    {
        foreach ($this->hiddenPatterns as $pattern) {
            if (str_contains($entryName, $pattern)) {
                return true;
            }
        }

        // Rejtett fájlok (ponttal kezdődő)
        $basename = basename($entryName);
        if (str_starts_with($basename, '.') && $basename !== '.') {
            return true;
        }

        return false;
    }

    /**
     * Képfájl ellenőrzése kiterjesztés alapján
     */
    protected function isImageFile(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($extension, $this->supportedExtensions);
    }

    /**
     * Folder path kinyerése a ZIP entry névből
     * A ZIP fájl neve lesz a gyökér mappa
     */
    protected function extractFolderPath(string $entryName, string $zipBaseName): string
    {
        $normalized = str_replace('\\', '/', $entryName);
        $parts = explode('/', $normalized);

        // Fájlnév eltávolítása (utolsó elem)
        array_pop($parts);

        // ZIP neve + belső mappa struktúra
        if (empty($parts)) {
            // Ha nincs belső mappa, csak a ZIP neve
            return $zipBaseName;
        }

        // ZIP neve / belső mappák
        return $zipBaseName . '/' . implode('/', $parts);
    }

    // isZipFile() a FileValidation trait-ből jön
}

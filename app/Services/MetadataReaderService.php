<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * IPTC/EXIF metaadat olvasó service.
 *
 * Képfájlokból kinyeri a Title/ObjectName mezőt a név-fájl párosításhoz.
 * Preferálja az exiftool-t ha elérhető, különben PHP fallback-et használ.
 *
 * SECURITY: Symfony Process komponenst használ shell_exec helyett
 * a parancs injekció megelőzésére.
 */
class MetadataReaderService
{
    /**
     * IPTC mező kódok prioritási sorrendben
     */
    private const IPTC_TITLE_CODES = [
        '2#005', // ObjectName (címke)
        '2#105', // Headline
        '2#120', // Caption/Abstract
    ];

    /**
     * Check if exiftool is available
     *
     * SECURITY: Symfony Process használata shell_exec helyett
     */
    public function isExifToolAvailable(): bool
    {
        $process = new Process(['which', 'exiftool']);
        $process->run();

        return $process->isSuccessful() && ! empty(trim($process->getOutput()));
    }

    /**
     * Extract title from image metadata
     *
     * @param  string  $filePath  Képfájl elérési útja
     * @return string|null Title vagy null ha nem található
     */
    public function extractTitle(string $filePath): ?string
    {
        if (! file_exists($filePath)) {
            Log::warning('MetadataReader: File not found', ['path' => $filePath]);

            return null;
        }

        // Preferáljuk az exiftool-t, ha elérhető
        if ($this->isExifToolAvailable()) {
            $title = $this->extractTitleWithExiftool($filePath);
            if ($title) {
                return $title;
            }
        }

        // PHP fallback
        return $this->extractTitleWithPhp($filePath);
    }

    /**
     * Extract title using exiftool (more reliable)
     *
     * SECURITY: Symfony Process használata shell_exec helyett
     * a parancs injekció megelőzésére.
     */
    protected function extractTitleWithExiftool(string $filePath): ?string
    {
        // SECURITY: Process parancs-lista formában - nincs shell interpoláció
        $process = new Process([
            'exiftool',
            '-s3',           // csak érték, nincs tag név
            '-ObjectName',
            '-Title',
            '-Headline',
            '-Description',
            '-XMP:Title',
            '-IPTC:Headline',
            $filePath,       // fájl útvonal biztonságosan átadva
        ]);

        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        // Az első nem üres sort vesszük
        $lines = explode("\n", trim($process->getOutput()));
        $output = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (! empty($line) && $line !== '-') {
                $output = $line;
                break;
            }
        }

        if (! empty($output)) {
            Log::debug('MetadataReader: Extracted title with exiftool', [
                'file' => basename($filePath),
                'title' => $output,
            ]);

            return $output;
        }

        return null;
    }

    /**
     * Extract title using PHP (fallback)
     */
    protected function extractTitleWithPhp(string $filePath): ?string
    {
        // Csak JPEG támogatott a PHP IPTC-hez
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg'])) {
            return null;
        }

        // IPTC adatok kinyerése
        $size = @getimagesize($filePath, $info);
        if (! $size || ! isset($info['APP13'])) {
            return null;
        }

        $iptc = @iptcparse($info['APP13']);
        if (! $iptc) {
            return null;
        }

        // Keresés a prioritási sorrendben
        foreach (self::IPTC_TITLE_CODES as $code) {
            if (isset($iptc[$code][0]) && ! empty(trim($iptc[$code][0]))) {
                $title = $this->cleanTitle($iptc[$code][0]);

                Log::debug('MetadataReader: Extracted title with PHP', [
                    'file' => basename($filePath),
                    'code' => $code,
                    'title' => $title,
                ]);

                return $title;
            }
        }

        return null;
    }

    /**
     * Clean and normalize title string
     */
    protected function cleanTitle(string $title): string
    {
        // UTF-8 konverzió ha szükséges (régi IPTC gyakran ISO-8859-1)
        $encoding = mb_detect_encoding($title, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $title = mb_convert_encoding($title, 'UTF-8', $encoding);
        }

        // NFC normalizálás (macOS NFD → NFC)
        if (class_exists('Normalizer')) {
            $title = \Normalizer::normalize($title, \Normalizer::FORM_C);
        }

        return trim($title);
    }

    /**
     * Bulk extract titles from multiple files
     *
     * @param  array  $filePaths  Fájl elérési utak tömbje
     * @return array<string, string|null> Asszociatív tömb: filepath => title
     */
    public function extractTitles(array $filePaths): array
    {
        $results = [];

        foreach ($filePaths as $filePath) {
            $results[$filePath] = $this->extractTitle($filePath);
        }

        return $results;
    }

    /**
     * Extract all relevant metadata (for debugging/admin)
     *
     * SECURITY: Symfony Process használata shell_exec helyett
     */
    public function extractAllMetadata(string $filePath): array
    {
        if (! file_exists($filePath)) {
            return [];
        }

        $metadata = [];

        if ($this->isExifToolAvailable()) {
            // SECURITY: Process parancs-lista formában
            $process = new Process(['exiftool', '-json', $filePath]);
            $process->run();

            if ($process->isSuccessful()) {
                $output = $process->getOutput();
                $decoded = json_decode($output, true);
                if (is_array($decoded) && isset($decoded[0])) {
                    $metadata = $decoded[0];
                }
            }
        }

        // Alap PHP EXIF
        if (function_exists('exif_read_data')) {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if (in_array($extension, ['jpg', 'jpeg', 'tiff'])) {
                $exif = @exif_read_data($filePath, null, true);
                if ($exif) {
                    $metadata['php_exif'] = $exif;
                }
            }
        }

        return $metadata;
    }
}

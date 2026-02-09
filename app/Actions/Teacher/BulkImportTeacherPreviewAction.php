<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Services\Teacher\TeacherMatchingService;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BulkImportTeacherPreviewAction
{
    public function __construct(
        private TeacherMatchingService $matchingService,
    ) {}

    /**
     * Nevek kinyerése (textarea/fájl) + matching pipeline futtatás.
     *
     * @return array Matching eredmények listája
     */
    public function execute(int $partnerId, int $schoolId, ?array $names, ?UploadedFile $file): array
    {
        $parsedNames = $names
            ? $this->parseTextNames($names)
            : $this->parseFileNames($file);

        if (empty($parsedNames)) {
            return [];
        }

        return $this->matchingService->matchNames($parsedNames, $partnerId, $schoolId);
    }

    /**
     * Szövegmezőből érkező nevek tisztítása.
     */
    private function parseTextNames(array $names): array
    {
        $result = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $result[] = $name;
            }
        }
        return array_unique($result);
    }

    /**
     * CSV/Excel/TXT fájlból nevek kinyerése.
     */
    private function parseFileNames(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['txt', 'csv'], true)) {
            return $this->parseTextFile($file);
        }

        return $this->parseSpreadsheetFile($file);
    }

    /**
     * TXT/CSV: soronként egy név.
     */
    private function parseTextFile(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());
        $lines = preg_split('/\r\n|\n|\r/', $content);
        $names = [];

        foreach ($lines as $line) {
            // CSV: vesszővel/pontosvesszővel elválasztott sorok kezelése
            $parts = preg_split('/[;,]/', $line);
            foreach ($parts as $part) {
                $name = trim($part);
                // Fejléc-szerű sorok kiszűrése
                if ($name !== '' && !$this->isHeaderLike($name)) {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * XLSX/XLS: első oszlop összes sora (fejléc kihagyva).
     */
    private function parseSpreadsheetFile(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (empty($rows)) {
            return [];
        }

        // Fejléc kihagyása ha "név"-szerű
        $firstRow = array_shift($rows);
        $firstCell = trim((string) ($firstRow['A'] ?? ''));
        if (!$this->isHeaderLike($firstCell)) {
            // Nem fejléc, vissza kell rakni
            array_unshift($rows, $firstRow);
        }

        $names = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['A'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    private function isHeaderLike(string $value): bool
    {
        $headers = ['név', 'name', 'tanár', 'teacher', 'tanárnév', 'teacher name'];
        return in_array(mb_strtolower($value), $headers, true);
    }
}

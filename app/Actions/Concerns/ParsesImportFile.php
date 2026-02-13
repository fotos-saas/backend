<?php

declare(strict_types=1);

namespace App\Actions\Concerns;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

trait ParsesImportFile
{
    protected function parseTextNames(array $names): array
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

    protected function parseFileNames(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['txt', 'csv'], true)) {
            return $this->parseTextFile($file);
        }

        return $this->parseSpreadsheetFile($file);
    }

    protected function parseTextFile(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());
        $lines = preg_split('/\r\n|\n|\r/', $content);
        $names = [];

        foreach ($lines as $line) {
            $parts = preg_split('/[;,]/', $line);
            foreach ($parts as $part) {
                $name = trim($part);
                if ($name !== '' && !$this->isHeaderLike($name)) {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    protected function parseSpreadsheetFile(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (empty($rows)) {
            return [];
        }

        $firstRow = array_shift($rows);
        $firstCell = trim((string) ($firstRow['A'] ?? ''));
        if (!$this->isHeaderLike($firstCell)) {
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

    protected function isHeaderLike(string $value): bool
    {
        $headers = ['név', 'name', 'tanár', 'teacher', 'tanárnév', 'teacher name', 'diák', 'student', 'diáknév', 'student name', 'tanuló'];
        return in_array(mb_strtolower($value), $headers, true);
    }
}

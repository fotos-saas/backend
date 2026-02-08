<?php

declare(strict_types=1);

namespace App\Actions\Partner;

use App\Models\TabloContact;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Kapcsolattartók importálása Excel fájlból.
 *
 * Kötelező oszlop: Név
 * Opcionális: Email, Telefon, Megjegyzés
 * Duplikát ellenőrzés: email alapján (ha van email)
 */
class ImportContactsFromExcelAction
{
    /**
     * @return array{imported: int, skipped: int, errors: int, details: array<int, string>}
     */
    public function execute(int $partnerId, string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (empty($rows)) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => 0, 'details' => ['Üres fájl']];
        }

        $headerRow = array_shift($rows);
        $columnMap = $this->mapColumns($headerRow);

        if ($columnMap['name'] === null) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => 0, 'details' => ['Hiányzó "Név" oszlop']];
        }

        $existingEmails = TabloContact::where('partner_id', $partnerId)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->map(fn ($e) => mb_strtolower($e))
            ->toArray();

        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $details = [];

        $rowNum = 2; // header = 1
        foreach ($rows as $row) {
            $name = trim((string) ($row[$columnMap['name']] ?? ''));

            if ($name === '') {
                $skipped++;
                $details[] = "#{$rowNum}: Hiányzó név - kihagyva";
                $rowNum++;
                continue;
            }

            $email = $columnMap['email'] !== null
                ? trim((string) ($row[$columnMap['email']] ?? ''))
                : '';

            $phone = $columnMap['phone'] !== null
                ? trim((string) ($row[$columnMap['phone']] ?? ''))
                : '';

            $note = $columnMap['note'] !== null
                ? trim((string) ($row[$columnMap['note']] ?? ''))
                : '';

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors++;
                $details[] = "#{$rowNum}: Érvénytelen email ({$email}) - kihagyva";
                $rowNum++;
                continue;
            }

            if ($email !== '' && in_array(mb_strtolower($email), $existingEmails, true)) {
                $skipped++;
                $details[] = "#{$rowNum}: Duplikált email ({$email}) - kihagyva";
                $rowNum++;
                continue;
            }

            TabloContact::create([
                'partner_id' => $partnerId,
                'name' => $name,
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'note' => $note ?: null,
            ]);

            if ($email !== '') {
                $existingEmails[] = mb_strtolower($email);
            }

            $imported++;
            $rowNum++;
        }

        return compact('imported', 'skipped', 'errors', 'details');
    }

    /**
     * @return array{name: string|null, email: string|null, phone: string|null, note: string|null}
     */
    private function mapColumns(array $headerRow): array
    {
        $map = ['name' => null, 'email' => null, 'phone' => null, 'note' => null];

        $aliases = [
            'name' => ['név', 'name', 'kontakt', 'kapcsolattartó'],
            'email' => ['email', 'e-mail', 'emailcím', 'e-mail cím'],
            'phone' => ['telefon', 'phone', 'tel', 'telefonszám', 'mobil'],
            'note' => ['megjegyzés', 'note', 'jegyzet', 'komment'],
        ];

        foreach ($headerRow as $colLetter => $cellValue) {
            $normalized = mb_strtolower(trim((string) $cellValue));

            foreach ($aliases as $field => $fieldAliases) {
                if (in_array($normalized, $fieldAliases, true)) {
                    $map[$field] = $colLetter;
                    break;
                }
            }
        }

        return $map;
    }
}

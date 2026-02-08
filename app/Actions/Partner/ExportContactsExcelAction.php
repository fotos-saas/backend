<?php

declare(strict_types=1);

namespace App\Actions\Partner;

use App\Models\TabloContact;
use App\Services\Search\SearchService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Kapcsolattartók exportálása Excel fájlba.
 *
 * Oszlopok: Név, Email, Telefon, Megjegyzés, Projektek, Iskolák, Elsődleges, Hívások, SMS-ek
 */
class ExportContactsExcelAction
{
    private const HEADER_STYLE = [
        'font' => [
            'bold' => true,
            'size' => 11,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4472C4'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ];

    private const ZEBRA_STYLE = [
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F2F2F2'],
        ],
    ];

    public function execute(int $partnerId, ?string $search = null): string
    {
        $contacts = $this->getContacts($partnerId, $search);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kapcsolattartók');

        $headers = ['Név', 'Email', 'Telefon', 'Megjegyzés', 'Projektek', 'Iskolák', 'Elsődleges', 'Hívások', 'SMS-ek'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray(self::HEADER_STYLE);

        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(30);
        $sheet->getColumnDimension('E')->setWidth(35);
        $sheet->getColumnDimension('F')->setWidth(30);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(10);
        $sheet->getColumnDimension('I')->setWidth(10);

        $sheet->setAutoFilter("A1:{$lastCol}1");

        $row = 2;
        foreach ($contacts as $contact) {
            $projects = $contact->projects;
            $projectNames = $projects->map(fn ($p) => $p->display_name)->implode(', ');
            $schoolNames = $projects->map(fn ($p) => $p->school?->name)->filter()->unique()->implode(', ');
            $isPrimary = $projects->contains(fn ($p) => $p->pivot->is_primary) ? 'Igen' : 'Nem';

            $sheet->setCellValue("A{$row}", $contact->name);
            $sheet->setCellValue("B{$row}", $contact->email ?? '');
            $sheet->setCellValue("C{$row}", $contact->phone ?? '');
            $sheet->setCellValue("D{$row}", $contact->note ?? '');
            $sheet->setCellValue("E{$row}", $projectNames);
            $sheet->setCellValue("F{$row}", $schoolNames);
            $sheet->setCellValue("G{$row}", $isPrimary);
            $sheet->setCellValue("H{$row}", $contact->call_count ?? 0);
            $sheet->setCellValue("I{$row}", $contact->sms_count ?? 0);

            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray(self::ZEBRA_STYLE);
            }

            $row++;
        }

        if ($row > 2) {
            $sheet->getStyle("A2:{$lastCol}" . ($row - 1))->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'contacts_excel_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, TabloContact>
     */
    private function getContacts(int $partnerId, ?string $search): \Illuminate\Database\Eloquent\Collection
    {
        $query = TabloContact::where('partner_id', $partnerId)
            ->with(['projects.school']);

        if ($search) {
            $query = app(SearchService::class)->apply($query, $search, [
                'columns' => ['name', 'email', 'phone'],
            ]);
        }

        return $query->orderBy('name')->get();
    }
}

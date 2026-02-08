<?php

declare(strict_types=1);

namespace App\Actions\Partner;

use App\Models\TabloPerson;
use App\Models\TabloProject;
use App\Models\TabloUserProgress;
use App\Models\TabloGuestSession;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Collection;

/**
 * Galéria monitoring Excel export.
 *
 * Egyetlen munkalap: személyek ABC sorrendben, szűrhető oszlopokkal.
 */
class ExportGalleryMonitoringExcelAction
{
    private const HEADER_STYLE = [
        'font' => [
            'bold' => true,
            'size' => 12,
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

    /**
     * Excel export generálása.
     *
     * @param  string|null  $filter  all|finalized|in_progress|not_started
     * @return string  Temp fájl elérési útja
     */
    public function execute(int $projectId, int $galleryId, ?string $filter = 'all'): string
    {
        $data = $this->collectData($projectId, $galleryId, $filter);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Monitoring');

        // Header
        $headers = ['Név', 'Típus', 'Státusz', 'Aktuális lépés', 'Retusált képek', 'Tablókép', 'Utolsó aktivitás', 'Véglegesítve'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $lastCol = chr(64 + count($headers)); // H
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray(self::HEADER_STYLE);

        // Oszlopszélességek
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(16);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(22);
        $sheet->getColumnDimension('H')->setWidth(22);

        // AutoFilter
        $sheet->setAutoFilter("A1:{$lastCol}1");

        // Adatok
        $row = 2;
        foreach ($data as $person) {
            $sheet->setCellValue("A{$row}", $person['name']);
            $sheet->setCellValue("B{$row}", $person['typeLabel']);
            $sheet->setCellValue("C{$row}", $person['statusLabel']);
            $sheet->setCellValue("D{$row}", $person['stepLabel']);
            $sheet->setCellValue("E{$row}", $person['retouchCount']);
            $sheet->setCellValue("F{$row}", $person['hasTabloPhoto'] ? 'Igen' : 'Nem');
            $sheet->setCellValue("G{$row}", $person['lastActivity'] ?? '-');
            $sheet->setCellValue("H{$row}", $person['finalizedAt'] ?? '-');

            // Zebra csíkozás
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F2F2F2'],
                    ],
                ]);
            }

            $row++;
        }

        // Bal igazítás az adatsorokra
        if ($row > 2) {
            $sheet->getStyle("A2:A" . ($row - 1))->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
        }

        // Mentés temp fájlba
        $tempFile = tempnam(sys_get_temp_dir(), 'gallery_monitoring_excel_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * Monitoring adatok összegyűjtése és szűrése.
     *
     * @return array<int, array>
     */
    private function collectData(int $projectId, int $galleryId, ?string $filter): array
    {
        $persons = TabloPerson::where('tablo_project_id', $projectId)
            ->with(['guestSession'])
            ->orderBy('name')
            ->get();

        $progressRecords = TabloUserProgress::where('tablo_gallery_id', $galleryId)
            ->get()
            ->keyBy('user_id');

        $guestSessions = TabloGuestSession::where('tablo_project_id', $projectId)
            ->where('verification_status', TabloGuestSession::VERIFICATION_VERIFIED)
            ->whereNotNull('tablo_person_id')
            ->get()
            ->keyBy('tablo_person_id');

        $result = [];

        foreach ($persons as $person) {
            $session = $guestSessions->get($person->id);
            $progress = $this->findProgress($session, $progressRecords);

            $workflowStatus = $progress?->workflow_status;

            // Szűrés
            if ($filter === 'finalized' && $workflowStatus !== TabloUserProgress::STATUS_FINALIZED) {
                continue;
            }
            if ($filter === 'in_progress' && $workflowStatus !== TabloUserProgress::STATUS_IN_PROGRESS) {
                continue;
            }
            if ($filter === 'not_started' && $session !== null) {
                continue;
            }

            $result[] = [
                'name' => $person->name,
                'typeLabel' => $person->type === 'student' ? 'Diák' : 'Tanár',
                'statusLabel' => $this->getStatusLabel($session, $workflowStatus),
                'stepLabel' => $this->getStepLabel($progress?->current_step),
                'retouchCount' => count($progress?->retouch_photo_ids ?? []),
                'hasTabloPhoto' => $progress?->tablo_photo_id !== null,
                'lastActivity' => $session?->last_activity_at?->format('Y.m.d H:i'),
                'finalizedAt' => $progress?->finalized_at?->format('Y.m.d H:i'),
            ];
        }

        return $result;
    }

    private function findProgress(?TabloGuestSession $session, Collection $progressRecords): ?TabloUserProgress
    {
        if (!$session || !$session->user_id) {
            return null;
        }

        return $progressRecords->get($session->user_id);
    }

    private function getStatusLabel(?TabloGuestSession $session, ?string $workflowStatus): string
    {
        if (!$session) {
            return 'Nem kezdte el';
        }

        return match ($workflowStatus) {
            TabloUserProgress::STATUS_FINALIZED => 'Véglegesített',
            TabloUserProgress::STATUS_IN_PROGRESS => 'Folyamatban',
            default => 'Megnyitotta',
        };
    }

    private function getStepLabel(?string $step): string
    {
        return match ($step) {
            'claiming' => 'Kiválasztás',
            'retouch' => 'Retusálás',
            'tablo' => 'Tablókép',
            'completed' => 'Befejezve',
            default => '-',
        };
    }
}

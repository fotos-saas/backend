<?php

declare(strict_types=1);

namespace App\Actions\Partner;

use App\Models\TabloPerson;
use App\Models\TabloUserProgress;
use App\Models\TabloGuestSession;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Galéria monitoring Excel export.
 *
 * 3 munkalap: Saját képek, Retusált, Tablókép.
 * Minden lapon: Név, Típus, kiválasztott kép(ek) fájlneve.
 *
 * A kiválasztott képek a TabloUserProgress.steps_data JSON-ból jönnek:
 * - claimed_media_ids: saját képek (Spatie media ID-k)
 * - retouch_media_ids: retusált képek
 * - tablo_media_id: tablókép
 */
class ExportGalleryMonitoringExcelAction
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

    public function execute(int $projectId, int $galleryId, ?string $filter = 'all'): string
    {
        $data = $this->collectData($projectId, $galleryId, $filter);

        $spreadsheet = new Spreadsheet();

        $this->buildSheet(
            $spreadsheet->getActiveSheet(),
            'Saját képek',
            $data,
            fn (array $row) => $row['claimedPhotos'],
        );

        $sheet2 = $spreadsheet->createSheet();
        $this->buildSheet(
            $sheet2,
            'Retusált',
            $data,
            fn (array $row) => $row['retouchPhotos'],
        );

        $sheet3 = $spreadsheet->createSheet();
        $this->buildSheet(
            $sheet3,
            'Tablókép',
            $data,
            fn (array $row) => $row['tabloPhoto'] ? [$row['tabloPhoto']] : [],
        );

        $spreadsheet->setActiveSheetIndex(0);

        $tempFile = tempnam(sys_get_temp_dir(), 'gallery_monitoring_excel_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * @param  callable(array): string[]  $photosFn
     */
    private function buildSheet(Worksheet $sheet, string $title, array $data, callable $photosFn): void
    {
        $sheet->setTitle($title);

        $sheet->setCellValue('A1', 'Név');
        $sheet->setCellValue('B1', 'Típus');
        $sheet->setCellValue('C1', 'Kiválasztott képek');

        $sheet->getStyle('A1:C1')->applyFromArray(self::HEADER_STYLE);

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(50);

        $sheet->setAutoFilter('A1:C1');

        $row = 2;
        foreach ($data as $person) {
            $photos = $photosFn($person);

            if (empty($photos)) {
                $sheet->setCellValue("A{$row}", $person['name']);
                $sheet->setCellValue("B{$row}", $person['typeLabel']);
                $sheet->setCellValue("C{$row}", '-');

                if ($row % 2 === 0) {
                    $sheet->getStyle("A{$row}:C{$row}")->applyFromArray(self::ZEBRA_STYLE);
                }

                $row++;
            } else {
                foreach ($photos as $photo) {
                    $sheet->setCellValue("A{$row}", $person['name']);
                    $sheet->setCellValue("B{$row}", $person['typeLabel']);
                    $sheet->setCellValue("C{$row}", $photo);

                    if ($row % 2 === 0) {
                        $sheet->getStyle("A{$row}:C{$row}")->applyFromArray(self::ZEBRA_STYLE);
                    }

                    $row++;
                }
            }
        }

        if ($row > 2) {
            $sheet->getStyle('A2:C' . ($row - 1))->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
        }
    }

    /**
     * @return array<int, array{name: string, typeLabel: string, claimedPhotos: string[], retouchPhotos: string[], tabloPhoto: string|null}>
     */
    private function collectData(int $projectId, int $galleryId, ?string $filter): array
    {
        $persons = TabloPerson::where('tablo_project_id', $projectId)
            ->orderBy('name')
            ->get();

        $guestSessions = TabloGuestSession::where('tablo_project_id', $projectId)
            ->where('verification_status', TabloGuestSession::VERIFICATION_VERIFIED)
            ->whereNotNull('tablo_person_id')
            ->get()
            ->keyBy('tablo_person_id');

        $progressRecords = TabloUserProgress::where('tablo_gallery_id', $galleryId)
            ->get()
            ->keyBy('user_id');

        // Összegyűjtjük az összes media ID-t a steps_data-ból (N+1 elkerülése)
        $allMediaIds = collect();
        $userIdMap = [];

        foreach ($persons as $person) {
            $session = $guestSessions->get($person->id);
            if (!$session || !$session->user_id) {
                continue;
            }

            $userId = $session->user_id;
            $userIdMap[$person->id] = $userId;
            $progress = $progressRecords->get($userId);

            if ($progress) {
                $stepsData = $progress->steps_data ?? [];
                foreach ($stepsData['claimed_media_ids'] ?? [] as $id) {
                    $allMediaIds->push($id);
                }
                foreach ($stepsData['retouch_media_ids'] ?? [] as $id) {
                    $allMediaIds->push($id);
                }
                if (!empty($stepsData['tablo_media_id'])) {
                    $allMediaIds->push($stepsData['tablo_media_id']);
                }
            }
        }

        // Batch: összes media file_name lekérdezése egyszerre
        $mediaNames = [];
        if ($allMediaIds->isNotEmpty()) {
            $mediaNames = Media::whereIn('id', $allMediaIds->unique())
                ->pluck('file_name', 'id')
                ->toArray();
        }

        $result = [];

        foreach ($persons as $person) {
            $session = $guestSessions->get($person->id);
            $userId = $userIdMap[$person->id] ?? null;
            $progress = $userId ? $progressRecords->get($userId) : null;
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

            $stepsData = $progress?->steps_data ?? [];

            // Saját képek fájlnevei
            $claimedPhotos = [];
            foreach ($stepsData['claimed_media_ids'] ?? [] as $mediaId) {
                if (isset($mediaNames[$mediaId])) {
                    $claimedPhotos[] = $mediaNames[$mediaId];
                }
            }

            // Retusált képek fájlnevei
            $retouchPhotos = [];
            foreach ($stepsData['retouch_media_ids'] ?? [] as $mediaId) {
                if (isset($mediaNames[$mediaId])) {
                    $retouchPhotos[] = $mediaNames[$mediaId];
                }
            }

            // Tablókép fájlneve
            $tabloPhotoName = null;
            $tabloMediaId = $stepsData['tablo_media_id'] ?? null;
            if ($tabloMediaId && isset($mediaNames[$tabloMediaId])) {
                $tabloPhotoName = $mediaNames[$tabloMediaId];
            }

            $result[] = [
                'name' => $person->name,
                'typeLabel' => $person->type === 'student' ? 'Diák' : 'Tanár',
                'claimedPhotos' => $claimedPhotos,
                'retouchPhotos' => $retouchPhotos,
                'tabloPhoto' => $tabloPhotoName,
            ];
        }

        return $result;
    }
}

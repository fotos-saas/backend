<?php

declare(strict_types=1);

namespace App\Actions\Partner;

use App\Models\Photo;
use App\Models\TabloPerson;
use App\Models\TabloUserProgress;
use App\Models\TabloGuestSession;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Galéria monitoring Excel export.
 *
 * 3 munkalap: Saját képek, Retusált, Tablókép.
 * Minden lapon: Név, Típus, kiválasztott kép(ek) fájlneve.
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

        // 1. munkalap: Saját képek
        $this->buildSheet(
            $spreadsheet->getActiveSheet(),
            'Saját képek',
            $data,
            fn (array $row) => $row['claimedPhotos'],
        );

        // 2. munkalap: Retusált
        $sheet2 = $spreadsheet->createSheet();
        $this->buildSheet(
            $sheet2,
            'Retusált',
            $data,
            fn (array $row) => $row['retouchPhotos'],
        );

        // 3. munkalap: Tablókép
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
     * Munkalap felépítése.
     *
     * @param  callable(array): string[]  $photosFn  Fájlnevek kinyerése egy személyből
     */
    private function buildSheet(Worksheet $sheet, string $title, array $data, callable $photosFn): void
    {
        $sheet->setTitle($title);

        // Header
        $sheet->setCellValue('A1', 'Név');
        $sheet->setCellValue('B1', 'Típus');
        $sheet->setCellValue('C1', 'Kiválasztott képek');

        $sheet->getStyle('A1:C1')->applyFromArray(self::HEADER_STYLE);

        // Oszlopszélességek
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(50);

        // AutoFilter
        $sheet->setAutoFilter('A1:C1');

        // Adatsorok
        $row = 2;
        foreach ($data as $person) {
            $photos = $photosFn($person);
            $photoList = implode(', ', $photos);

            $sheet->setCellValue("A{$row}", $person['name']);
            $sheet->setCellValue("B{$row}", $person['typeLabel']);
            $sheet->setCellValue("C{$row}", $photoList ?: '-');

            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:C{$row}")->applyFromArray(self::ZEBRA_STYLE);
            }

            $row++;
        }

        // Bal igazítás
        if ($row > 2) {
            $sheet->getStyle('A2:C' . ($row - 1))->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
        }
    }

    /**
     * Monitoring adatok összegyűjtése a kiválasztott fájlnevekkel.
     *
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

        // Összegyűjtjük az összes szükséges photo ID-t egyszerre (N+1 elkerülése)
        $allPhotoIds = collect();
        $userIdMap = []; // person_id => user_id

        foreach ($persons as $person) {
            $session = $guestSessions->get($person->id);
            if (!$session || !$session->user_id) {
                continue;
            }

            $userId = $session->user_id;
            $userIdMap[$person->id] = $userId;
            $progress = $progressRecords->get($userId);

            if ($progress) {
                foreach ($progress->retouch_photo_ids ?? [] as $id) {
                    $allPhotoIds->push($id);
                }
                if ($progress->tablo_photo_id) {
                    $allPhotoIds->push($progress->tablo_photo_id);
                }
            }
        }

        // Saját képek (claimed) - user_id alapján csoportosítva
        $userIds = array_values($userIdMap);
        $claimedByUser = Photo::whereIn('claimed_by_user_id', $userIds)
            ->get(['id', 'original_filename', 'claimed_by_user_id'])
            ->groupBy('claimed_by_user_id');

        // Retusált + tablókép
        $photoNames = [];
        if ($allPhotoIds->isNotEmpty()) {
            $photoNames = Photo::whereIn('id', $allPhotoIds->unique())
                ->pluck('original_filename', 'id')
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

            // Saját képek fájlnevei
            $personClaimedPhotos = [];
            if ($userId && $claimedByUser->has($userId)) {
                $personClaimedPhotos = $claimedByUser->get($userId)
                    ->pluck('original_filename')
                    ->toArray();
            }

            // Retusált képek fájlnevei
            $retouchPhotos = [];
            foreach ($progress?->retouch_photo_ids ?? [] as $photoId) {
                if (isset($photoNames[$photoId])) {
                    $retouchPhotos[] = $photoNames[$photoId];
                }
            }

            // Tablókép fájlneve
            $tabloPhotoName = null;
            if ($progress?->tablo_photo_id && isset($photoNames[$progress->tablo_photo_id])) {
                $tabloPhotoName = $photoNames[$progress->tablo_photo_id];
            }

            $result[] = [
                'name' => $person->name,
                'typeLabel' => $person->type === 'student' ? 'Diák' : 'Tanár',
                'claimedPhotos' => $personClaimedPhotos,
                'retouchPhotos' => $retouchPhotos,
                'tabloPhoto' => $tabloPhotoName,
            ];
        }

        return $result;
    }
}

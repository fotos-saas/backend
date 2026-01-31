<?php

namespace App\Services;

use App\Models\PartnerAlbum;
use App\Models\Photo;
use App\Models\TabloUserProgress;
use App\Models\User;
use App\Models\WorkSession;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class ExcelExportService
{
    /**
     * Generál egy Excel fájlt a kiválasztott felhasználók képadataival
     *
     * @param WorkSession $workSession
     * @param array $userIds
     * @return string Excel fájl teljes elérési útja (temp)
     */
    public function generateManagerExcel(WorkSession $workSession, array $userIds): string
    {
        $spreadsheet = new Spreadsheet();

        // Eltávolítjuk a default sheet-et
        $spreadsheet->removeSheetByIndex(0);

        // 3 munkalap létrehozása
        $this->createWorksheet($spreadsheet, 'Saját képek', $workSession, $userIds, 'claimed');
        $this->createWorksheet($spreadsheet, 'Returálandó', $workSession, $userIds, 'retus');
        $this->createWorksheet($spreadsheet, 'Tablókép', $workSession, $userIds, 'tablo');

        // Aktív sheet beállítása az elsőre
        $spreadsheet->setActiveSheetIndex(0);

        // Temp fájl létrehozása
        $tempFile = tempnam(sys_get_temp_dir(), 'excel_export_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * Egy munkalap létrehozása és feltöltése adatokkal
     *
     * @param Spreadsheet $spreadsheet
     * @param string $sheetTitle
     * @param WorkSession $workSession
     * @param array $userIds
     * @param string $photoType 'claimed', 'retus', 'tablo'
     */
    private function createWorksheet(
        Spreadsheet $spreadsheet,
        string $sheetTitle,
        WorkSession $workSession,
        array $userIds,
        string $photoType
    ): void {
        // Új munkalap létrehozása
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($sheetTitle);

        // Header sor (A1: Név, B1: Osztály, C1: Képfájl neve)
        $sheet->setCellValue('A1', 'Név');
        $sheet->setCellValue('B1', 'Osztály');
        $sheet->setCellValue('C1', 'Képfájl neve');

        // Header formázás
        $headerStyle = [
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

        $sheet->getStyle('A1:C1')->applyFromArray($headerStyle);

        // Oszlopszélesség
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(25);

        // Adatok lekérése és feltöltése
        $userData = $this->getUserPhotoFileNames($workSession, $userIds, $photoType);

        $row = 2; // Adatok a 2. sortól kezdődnek
        foreach ($userData as $data) {
            $sheet->setCellValue("A{$row}", $data['name']);
            $sheet->setCellValue("B{$row}", $data['class_name']);
            $sheet->setCellValue("C{$row}", $data['photo_filename']);

            // Adatsorok formázása (bal igazítás)
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            // Képfájl neve - közép igazítás
            $sheet->getStyle("C{$row}")->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            $row++;
        }

        // Zebra csíkozás (páros sorok világosabb háttérrel)
        if ($row > 2) {
            for ($i = 2; $i < $row; $i++) {
                if ($i % 2 === 0) {
                    $sheet->getStyle("A{$i}:C{$i}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F2F2F2'],
                        ],
                    ]);
                }
            }
        }
    }

    /**
     * Felhasználók képfájlneveinek lekérése
     * Minden képhez külön sor jön létre
     *
     * @param WorkSession $workSession
     * @param array $userIds
     * @param string $photoType
     * @return array [['name' => 'User Name', 'class_name' => 'School - Class', 'photo_filename' => 'IMG_1234'], ...]
     */
    private function getUserPhotoFileNames(WorkSession $workSession, array $userIds, string $photoType): array
    {
        $userData = [];

        // Felhasználók lekérése osztály relációval (tabloRegistration.schoolClass)
        $users = User::with('tabloRegistration.schoolClass')
            ->whereIn('id', $userIds)
            ->get()
            ->sortBy(function($user) {
                // Osztály szerint rendezés (NULL értékek a végére)
                return $user->tabloRegistration?->schoolClass?->label ?? 'ZZZZ';
            });

        foreach ($users as $user) {
            // TabloUserProgress lekérése
            $progress = TabloUserProgress::where(function ($query) use ($workSession, $user) {
                $query->where('work_session_id', $workSession->id)
                    ->where('user_id', $user->id)
                    ->orWhere(function ($q) use ($workSession, $user) {
                        $q->whereHas('childWorkSession', function ($csq) use ($workSession) {
                            $csq->where('parent_work_session_id', $workSession->id);
                        })->where('user_id', $user->id);
                    });
            })->first();

            if (!$progress) {
                continue;
            }

            $stepsData = $progress->steps_data ?? [];
            $photoIds = [];

            // Képek ID-k meghatározása típus szerint
            switch ($photoType) {
                case 'claimed':
                    $photoIds = $stepsData['claimed_photo_ids'] ?? [];
                    break;

                case 'retus':
                    $photoIds = $stepsData['retouch_photo_ids'] ?? [];
                    break;

                case 'tablo':
                    $tabloPhotoId = $stepsData['tablo_photo_id'] ?? null;
                    $photoIds = $tabloPhotoId ? [$tabloPhotoId] : [];
                    break;
            }

            // Képek lekérése és fájlnevek hozzáadása
            if (!empty($photoIds)) {
                $photos = Photo::whereIn('id', $photoIds)->get();

                foreach ($photos as $photo) {
                    // Eredeti fájlnév használata (kiterjesztés nélkül)
                    $filename = $photo->original_filename ?? 'Ismeretlen.jpg';
                    $filenameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

                    // Osztály név (csak label, iskola nélkül)
                    $className = '-';
                    if ($user->tabloRegistration && $user->tabloRegistration->schoolClass) {
                        $className = $user->tabloRegistration->schoolClass->label;
                    }

                    $userData[] = [
                        'name' => $user->name,
                        'class_name' => $className,
                        'photo_filename' => $filenameWithoutExt,
                    ];
                }
            }
        }

        return $userData;
    }

    /**
     * Partner album kiválasztott képek fájlneveinek exportálása Excelbe.
     *
     * @param  PartnerAlbum  $album  Album modell
     * @param  array<int>  $photoIds  Média ID-k
     * @return string  Excel fájl teljes elérési útja (temp)
     */
    public function generatePartnerAlbumExcel(PartnerAlbum $album, array $photoIds): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kiválasztott képek');

        // Header
        $sheet->setCellValue('A1', 'Sorszám');
        $sheet->setCellValue('B1', 'Fájlnév');

        // Header formázás
        $headerStyle = [
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

        $sheet->getStyle('A1:B1')->applyFromArray($headerStyle);

        // Oszlopszélesség
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(40);

        // Adatok
        $media = $album->getMedia('photos')->whereIn('id', $photoIds)->sortBy('order_column');
        $row = 2;
        $index = 1;

        foreach ($media as $item) {
            // Fájlnév kiterjesztés nélkül
            $filenameWithoutExt = pathinfo($item->file_name, PATHINFO_FILENAME);

            $sheet->setCellValue("A{$row}", $index);
            $sheet->setCellValue("B{$row}", $filenameWithoutExt);

            $row++;
            $index++;
        }

        // Zebra csíkozás
        for ($i = 2; $i < $row; $i++) {
            if ($i % 2 === 0) {
                $sheet->getStyle("A{$i}:B{$i}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F2F2F2'],
                    ],
                ]);
            }
        }

        // Adatsorok középre igazítás az A oszlopban
        if ($row > 2) {
            $sheet->getStyle("A2:A" . ($row - 1))->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);
        }

        // Temp fájl létrehozása
        $tempFile = tempnam(sys_get_temp_dir(), 'partner_album_excel_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }
}

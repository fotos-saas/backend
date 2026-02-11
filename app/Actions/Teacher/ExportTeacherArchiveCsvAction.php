<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Models\TeacherArchive;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportTeacherArchiveCsvAction
{
    public function execute(int $partnerId): StreamedResponse
    {
        // Linked group térkép
        $linkedGroups = $this->buildLinkedGroups($partnerId);

        // Összes aktív archív tanár + media
        $teachers = TeacherArchive::forPartner($partnerId)
            ->active()
            ->with('activePhoto', 'school')
            ->orderBy('school_id')
            ->orderBy('canonical_name')
            ->get();

        // school_id → linked group összes school_id-ja
        $rows = [];
        foreach ($teachers as $t) {
            $groupSchoolIds = $linkedGroups[$t->school_id] ?? [$t->school_id];
            sort($groupSchoolIds);
            $schoolIdsStr = implode(';', $groupSchoolIds);

            $fileName = $t->activePhoto?->file_name;
            $oldId = $fileName ? $this->extractOldId($fileName) : '';

            $rows[] = [
                'school_ids' => $schoolIdsStr,
                'school_name' => $t->school?->name ?? '',
                'archive_id' => $t->id,
                'old_id' => $oldId,
                'name' => $t->full_display_name,
                'has_photo' => $t->active_photo_id ? 'igen' : 'nem',
                'file_name' => $fileName ?? '',
            ];
        }

        // Rendezés: school_ids group → név
        usort($rows, function (array $a, array $b) {
            $cmp = strcmp($a['school_ids'], $b['school_ids']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp($a['name'], $b['name']);
        });

        return new StreamedResponse(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 Excel-hez
            fwrite($handle, "\xEF\xBB\xBF");

            // Header
            fputcsv($handle, [
                'Iskola ID-k',
                'Iskola név',
                'Archív ID',
                'Régi ID',
                'Név',
                'Van fotó',
                'Fájlnév',
            ], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['school_ids'],
                    $row['school_name'],
                    $row['archive_id'],
                    $row['old_id'],
                    $row['name'],
                    $row['has_photo'],
                    $row['file_name'],
                ], ';');
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="teacher_archive_export.csv"',
        ]);
    }

    /**
     * Fájlnévből kinyeri a régi ID-t: "lakatos_adam_10476.jpeg" → "10476"
     */
    private function extractOldId(string $fileName): string
    {
        $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
        if (preg_match('/_(\d+)$/', $nameWithoutExt, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * school_id → az összes linked school_id tömb
     */
    private function buildLinkedGroups(int $partnerId): array
    {
        $links = DB::table('partner_schools')
            ->where('partner_id', $partnerId)
            ->whereNotNull('linked_group')
            ->get(['school_id', 'linked_group']);

        if ($links->isEmpty()) {
            return [];
        }

        $groups = $links->groupBy('linked_group');
        $map = [];

        foreach ($groups as $members) {
            $memberIds = $members->pluck('school_id')->toArray();
            foreach ($memberIds as $mid) {
                $map[$mid] = $memberIds;
            }
        }

        return $map;
    }
}

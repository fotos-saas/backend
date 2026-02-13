<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\StudentArchive;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportStudentArchiveCsvAction
{
    public function execute(int $partnerId): StreamedResponse
    {
        $students = StudentArchive::forPartner($partnerId)
            ->active()
            ->with('activePhoto', 'school')
            ->orderBy('school_id')
            ->orderBy('class_name')
            ->orderBy('canonical_name')
            ->get();

        $rows = [];
        foreach ($students as $s) {
            $rows[] = [
                'school_name' => $s->school?->name ?? '',
                'archive_id' => $s->id,
                'name' => $s->canonical_name,
                'class_name' => $s->class_name ?? '',
                'has_photo' => $s->active_photo_id ? 'igen' : 'nem',
                'file_name' => $s->activePhoto?->file_name ?? '',
            ];
        }

        return new StreamedResponse(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 Excel-hez
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Iskola név',
                'Archív ID',
                'Név',
                'Osztály',
                'Van fotó',
                'Fájlnév',
            ], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['school_name'],
                    $row['archive_id'],
                    $row['name'],
                    $row['class_name'],
                    $row['has_photo'],
                    $row['file_name'],
                ], ';');
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="student_archive_export.csv"',
        ]);
    }
}

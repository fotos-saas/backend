<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Models\TeacherArchive;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class BulkUploadTeacherPhotosAction
{
    private UploadTeacherPhotoAction $uploadAction;

    public function __construct()
    {
        $this->uploadAction = new UploadTeacherPhotoAction();
    }

    /**
     * @param  array<string, int>  $assignments  filename => teacher_id
     * @param  array<string, UploadedFile>  $fileMap  filename => UploadedFile
     * @return array{uploaded: int, skipped: int, failed: int, results: array}
     */
    public function execute(array $assignments, array $fileMap, int $year, bool $setActive = false): array
    {
        $uploaded = 0;
        $skipped = 0;
        $failed = 0;
        $results = [];

        foreach ($assignments as $filename => $teacherId) {
            if (!isset($fileMap[$filename])) {
                $skipped++;
                $results[] = ['filename' => $filename, 'status' => 'skipped', 'reason' => 'Fájl nem található'];
                continue;
            }

            $teacher = TeacherArchive::find($teacherId);
            if (!$teacher) {
                $skipped++;
                $results[] = ['filename' => $filename, 'status' => 'skipped', 'reason' => 'Tanár nem található'];
                continue;
            }

            try {
                $this->uploadAction->execute($teacher, $fileMap[$filename], $year, $setActive);
                $uploaded++;
                $results[] = ['filename' => $filename, 'status' => 'success', 'teacher_id' => $teacherId];
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Bulk photo upload hiba', [
                    'filename' => $filename,
                    'teacher_id' => $teacherId,
                    'error' => $e->getMessage(),
                ]);
                $results[] = ['filename' => $filename, 'status' => 'failed', 'reason' => 'Feltöltési hiba'];
            }
        }

        return [
            'uploaded' => $uploaded,
            'skipped' => $skipped,
            'failed' => $failed,
            'results' => $results,
        ];
    }
}

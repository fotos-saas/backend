<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\StudentArchive;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class BulkUploadStudentPhotosAction
{
    private UploadStudentPhotoAction $uploadAction;

    public function __construct()
    {
        $this->uploadAction = new UploadStudentPhotoAction();
    }

    /**
     * @param  array<string, int>  $assignments  filename => student_id
     * @param  array<string, UploadedFile>  $fileMap  filename => UploadedFile
     * @return array{uploaded: int, skipped: int, failed: int, results: array}
     */
    public function execute(array $assignments, array $fileMap, int $year, bool $setActive = false): array
    {
        $uploaded = 0;
        $skipped = 0;
        $failed = 0;
        $results = [];

        foreach ($assignments as $filename => $studentId) {
            if (!isset($fileMap[$filename])) {
                $skipped++;
                $results[] = ['filename' => $filename, 'status' => 'skipped', 'reason' => 'Fájl nem található'];
                continue;
            }

            $student = StudentArchive::find($studentId);
            if (!$student) {
                $skipped++;
                $results[] = ['filename' => $filename, 'status' => 'skipped', 'reason' => 'Diák nem található'];
                continue;
            }

            try {
                $this->uploadAction->execute($student, $fileMap[$filename], $year, $setActive);
                $uploaded++;
                $results[] = ['filename' => $filename, 'status' => 'success', 'student_id' => $studentId];
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Bulk photo upload hiba', [
                    'filename' => $filename,
                    'student_id' => $studentId,
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

<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Actions\Concerns\BulkUploadsArchivePhotos;
use App\Models\StudentArchive;
use Illuminate\Http\UploadedFile;

class BulkUploadStudentPhotosAction
{
    use BulkUploadsArchivePhotos;

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
        return $this->executeBulkUpload($assignments, $fileMap, $year, $setActive, StudentArchive::class, 'Diák', 'student_id');
    }

    protected function getUploadAction(): object
    {
        return $this->uploadAction;
    }
}

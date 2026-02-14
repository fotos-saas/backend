<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Actions\Concerns\BulkUploadsArchivePhotos;
use App\Models\TeacherArchive;
use Illuminate\Http\UploadedFile;

class BulkUploadTeacherPhotosAction
{
    use BulkUploadsArchivePhotos;

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
        return $this->executeBulkUpload($assignments, $fileMap, $year, $setActive, TeacherArchive::class, 'Tanár', 'teacher_id');
    }

    protected function getUploadAction(): object
    {
        return $this->uploadAction;
    }
}

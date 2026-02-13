<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Actions\Concerns\ManagesArchivePhotos;
use App\Models\StudentArchive;
use App\Models\StudentChangeLog;
use App\Models\StudentPhoto;
use Illuminate\Http\UploadedFile;

class UploadStudentPhotoAction
{
    use ManagesArchivePhotos;

    public function execute(StudentArchive $student, UploadedFile $file, int $year, bool $setActive = false): StudentPhoto
    {
        $photo = $this->uploadAndAttachPhoto(
            $student, $file, $year, 'student_photos', StudentPhoto::class, 'student_id'
        );

        if ($setActive) {
            $this->setActivePhoto($student, $photo);
        }

        StudentChangeLog::create([
            'student_id' => $student->id,
            'user_id' => auth()->id(),
            'change_type' => 'photo_uploaded',
            'new_value' => $photo->media->file_name,
            'metadata' => ['year' => $year, 'media_id' => $photo->media_id],
        ]);

        return $photo;
    }

    public function setActive(StudentArchive $student, StudentPhoto $photo): void
    {
        $this->setActivePhoto($student, $photo);
    }
}

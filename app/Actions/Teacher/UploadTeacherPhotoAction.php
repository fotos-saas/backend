<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Actions\Concerns\ManagesArchivePhotos;
use App\Models\TeacherArchive;
use App\Models\TeacherChangeLog;
use App\Models\TeacherPhoto;
use Illuminate\Http\UploadedFile;

class UploadTeacherPhotoAction
{
    use ManagesArchivePhotos;

    public function execute(TeacherArchive $teacher, UploadedFile $file, int $year, bool $setActive = false): TeacherPhoto
    {
        $photo = $this->uploadAndAttachPhoto(
            $teacher, $file, $year, 'teacher_photos', TeacherPhoto::class, 'teacher_id'
        );

        if ($setActive) {
            $this->setActivePhoto($teacher, $photo);
        }

        TeacherChangeLog::create([
            'teacher_id' => $teacher->id,
            'user_id' => auth()->id(),
            'change_type' => 'photo_uploaded',
            'new_value' => $photo->media->file_name,
            'metadata' => ['year' => $year, 'media_id' => $photo->media_id],
        ]);

        return $photo;
    }

    public function setActive(TeacherArchive $teacher, TeacherPhoto $photo): void
    {
        $this->setActivePhoto($teacher, $photo);
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Models\TeacherArchive;
use App\Models\TeacherChangeLog;
use App\Models\TeacherPhoto;
use Illuminate\Http\UploadedFile;

class UploadTeacherPhotoAction
{
    public function execute(TeacherArchive $teacher, UploadedFile $file, int $year, bool $setActive = false): TeacherPhoto
    {
        // Spatie media feltöltés
        $media = $teacher->addMedia($file)->toMediaCollection('teacher_photos');

        // TeacherPhoto rekord
        $teacherPhoto = TeacherPhoto::create([
            'teacher_id' => $teacher->id,
            'media_id' => $media->id,
            'year' => $year,
            'is_active' => false,
            'uploaded_by' => auth()->id(),
        ]);

        // Aktív beállítás (ha kérték, vagy ha ez az első fotó)
        $existingActiveCount = $teacher->photos()->where('is_active', true)->count();
        if ($setActive || $existingActiveCount === 0) {
            $this->setActive($teacher, $teacherPhoto);
        }

        // Changelog
        TeacherChangeLog::create([
            'teacher_id' => $teacher->id,
            'user_id' => auth()->id(),
            'change_type' => 'photo_uploaded',
            'new_value' => $media->file_name,
            'metadata' => ['year' => $year, 'media_id' => $media->id],
        ]);

        return $teacherPhoto->load('media');
    }

    public function setActive(TeacherArchive $teacher, TeacherPhoto $photo): void
    {
        // Korábbi aktív fotó deaktiválás
        $teacher->photos()->where('is_active', true)->update(['is_active' => false]);

        // Új aktív fotó beállítás
        $photo->update(['is_active' => true]);
        $teacher->update(['active_photo_id' => $photo->media_id]);
    }
}

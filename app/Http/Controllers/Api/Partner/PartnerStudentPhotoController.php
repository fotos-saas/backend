<?php

namespace App\Http\Controllers\Api\Partner;

use App\Actions\Student\UploadStudentPhotoAction;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\UploadStudentPhotoRequest;
use App\Models\StudentArchive;
use App\Models\StudentChangeLog;
use Illuminate\Http\JsonResponse;

class PartnerStudentPhotoController extends Controller
{
    use PartnerAuthTrait;

    public function uploadPhoto(UploadStudentPhotoRequest $request, int $id, UploadStudentPhotoAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $student = StudentArchive::forPartner($partnerId)->findOrFail($id);

        $photo = $action->execute(
            $student,
            $request->file('photo'),
            (int) $request->validated('year'),
            (bool) $request->input('set_active', false),
        );

        return response()->json([
            'success' => true,
            'message' => 'Fotó sikeresen feltöltve',
            'data' => [
                'id' => $photo->id,
                'mediaId' => $photo->media_id,
                'year' => $photo->year,
                'isActive' => $photo->is_active,
                'url' => $photo->media?->getUrl(),
                'thumbUrl' => $photo->media?->getUrl('thumb'),
                'fileName' => $photo->media?->file_name,
            ],
        ], 201);
    }

    public function setActivePhoto(int $id, int $photoId, UploadStudentPhotoAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $student = StudentArchive::forPartner($partnerId)->findOrFail($id);
        $photo = $student->photos()->findOrFail($photoId);

        $action->setActive($student, $photo);

        StudentChangeLog::create([
            'student_id' => $student->id,
            'user_id' => auth()->id(),
            'change_type' => 'active_photo_changed',
            'new_value' => $photo->media?->file_name,
            'metadata' => ['photo_id' => $photo->id, 'year' => $photo->year],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Aktív fotó beállítva',
        ]);
    }

    public function deletePhoto(int $id, int $photoId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $student = StudentArchive::forPartner($partnerId)->findOrFail($id);
        $photo = $student->photos()->findOrFail($photoId);

        $fileName = $photo->media?->file_name;
        $wasActive = $photo->is_active;

        if ($photo->media) {
            $photo->media->delete();
        }

        $photo->delete();

        if ($wasActive) {
            $student->update(['active_photo_id' => null]);

            $nextPhoto = $student->photos()->orderByDesc('year')->first();
            if ($nextPhoto) {
                (new UploadStudentPhotoAction())->setActive($student, $nextPhoto);
            }
        }

        StudentChangeLog::create([
            'student_id' => $student->id,
            'user_id' => auth()->id(),
            'change_type' => 'photo_deleted',
            'old_value' => $fileName,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fotó sikeresen törölve',
        ]);
    }
}

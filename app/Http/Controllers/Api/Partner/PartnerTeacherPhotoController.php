<?php

namespace App\Http\Controllers\Api\Partner;

use App\Actions\Teacher\UploadTeacherPhotoAction;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\UploadTeacherPhotoRequest;
use App\Models\TeacherArchive;
use App\Models\TeacherChangeLog;
use App\Models\TeacherPhoto;
use Illuminate\Http\JsonResponse;

class PartnerTeacherPhotoController extends Controller
{
    use PartnerAuthTrait;

    public function uploadPhoto(UploadTeacherPhotoRequest $request, int $id, UploadTeacherPhotoAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $teacher = TeacherArchive::forPartner($partnerId)->findOrFail($id);

        $photo = $action->execute(
            $teacher,
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

    public function setActivePhoto(int $id, int $photoId, UploadTeacherPhotoAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $teacher = TeacherArchive::forPartner($partnerId)->findOrFail($id);
        $photo = $teacher->photos()->findOrFail($photoId);

        $action->setActive($teacher, $photo);

        TeacherChangeLog::create([
            'teacher_id' => $teacher->id,
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
        $teacher = TeacherArchive::forPartner($partnerId)->findOrFail($id);
        $photo = $teacher->photos()->findOrFail($photoId);

        $fileName = $photo->media?->file_name;
        $wasActive = $photo->is_active;

        // Spatie media törlés
        if ($photo->media) {
            $photo->media->delete();
        }

        $photo->delete();

        // Ha az aktív fotót törölték, active_photo_id nullázása
        if ($wasActive) {
            $teacher->update(['active_photo_id' => null]);

            // Következő fotó aktiválás (ha van)
            $nextPhoto = $teacher->photos()->orderByDesc('year')->first();
            if ($nextPhoto) {
                (new UploadTeacherPhotoAction())->setActive($teacher, $nextPhoto);
            }
        }

        TeacherChangeLog::create([
            'teacher_id' => $teacher->id,
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

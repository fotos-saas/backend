<?php

declare(strict_types=1);

namespace App\Actions\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

trait ManagesArchivePhotos
{
    public function uploadAndAttachPhoto(Model $archiveEntry, UploadedFile $file, int $year, string $collection, string $photoModel, string $foreignKey): Model
    {
        $media = $archiveEntry->addMedia($file)->toMediaCollection($collection);

        $photo = $photoModel::create([
            $foreignKey => $archiveEntry->id,
            'media_id' => $media->id,
            'year' => $year,
            'is_active' => false,
            'uploaded_by' => auth()->id(),
        ]);

        $existingActiveCount = $archiveEntry->photos()->where('is_active', true)->count();
        if ($existingActiveCount === 0) {
            $this->setActivePhoto($archiveEntry, $photo);
        }

        return $photo->load('media');
    }

    public function setActivePhoto(Model $archiveEntry, Model $photo): void
    {
        $archiveEntry->photos()->where('is_active', true)->update(['is_active' => false]);
        $photo->update(['is_active' => true]);
        $archiveEntry->update(['active_photo_id' => $photo->media_id]);
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhotoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'albumId' => $this->album_id,
            'url' => url("/api/photos/{$this->id}/preview"),
            'w' => $this->width,
            'h' => $this->height,
            'orientation' => $this->width > $this->height ? 'landscape' : 'portrait',
            'path' => $this->path,
            'assignedUserId' => $this->assigned_user_id,

            // Optional relationships
            'assignedUser' => $this->whenLoaded('assignedUser', fn () => [
                'id' => $this->assignedUser->id,
                'name' => $this->assignedUser->name,
            ]),
            'faceGroups' => $this->whenLoaded('faceGroups', fn () => $this->faceGroups->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'albumId' => $group->album_id,
                'representativePhotoId' => $group->representative_photo_id,
            ])),
        ];
    }
}

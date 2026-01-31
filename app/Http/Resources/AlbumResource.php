<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlbumResource extends JsonResource
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
            'createdByUserId' => $this->created_by_user_id,
            'name' => $this->name ?? $this->title,
            'title' => $this->title,
            'date' => $this->date?->format('Y-m-d'),
            'status' => $this->status ?? 'active',
            'thumbnail' => $this->getThumbnailUrl(),
            'flags' => $this->flags ?? \App\Models\Album::getDefaultFlags(),
            'visibility' => $this->visibility,

            // Pricing and work session
            'packageId' => $this->package_id,
            'packageName' => $this->package?->name,
            'packagePrice' => $this->package?->price,
            'priceListId' => $this->price_list_id,
            'workSessionId' => $this->getWorkSessionId(),
            'workSessionDigitCodeEnabled' => $this->getDigitCodeEnabled(),
            'workSessionAllowInvitations' => $this->getAllowInvitations(),
            'isTabloMode' => $this->getTabloModeStatus(),

            // Optional relationships
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ]),
            'photos' => PhotoResource::collection($this->whenLoaded('photos')),
            'photosCount' => $this->when(isset($this->photos_count), $this->photos_count),
            'schoolClasses' => SchoolClassResource::collection($this->whenLoaded('schoolClasses')),
            'faceGroups' => $this->whenLoaded('faceGroups', fn () => $this->faceGroups->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'albumId' => $group->album_id,
                'representativePhotoId' => $group->representative_photo_id,
                'photosCount' => $group->photos()->count(),
            ])),
        ];
    }

    /**
     * Get thumbnail URL for the album
     */
    protected function getThumbnailUrl(): ?string
    {
        // If explicit thumbnail is set, use it
        if ($this->thumbnail) {
            return $this->thumbnail;
        }

        // Otherwise, use first photo's preview URL
        if ($this->relationLoaded('photos') && $this->photos->isNotEmpty()) {
            $firstPhoto = $this->photos->first();

            return url("/photos/{$firstPhoto->id}/preview?w=400");
        }

        return null;
    }

    /**
     * Get work session ID from associated work sessions
     * Priority: User-specific work session > First work session
     */
    protected function getWorkSessionId(): ?int
    {
        // Get current authenticated user
        $user = request()->user();

        // If user is authenticated, get work sessions that belong to the user
        if ($user) {
            // Check loaded work sessions first
            if ($this->relationLoaded('workSessions') && $this->workSessions->isNotEmpty()) {
                $userWorkSession = $this->workSessions->first(function ($session) use ($user) {
                    return $session->users()->where('user_id', $user->id)->exists();
                });

                if ($userWorkSession) {
                    return $userWorkSession->id;
                }
            }

            // If not loaded, query directly
            $userWorkSession = $this->workSessions()
                ->whereHas('users', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->first();

            if ($userWorkSession) {
                return $userWorkSession->id;
            }
        }

        // Fallback: If no user or user has no work session for this album
        // Return first work session (original behavior)
        if ($this->relationLoaded('workSessions') && $this->workSessions->isNotEmpty()) {
            return $this->workSessions->first()->id;
        }

        $firstSession = $this->workSessions()->first();

        return $firstSession?->id;
    }

    /**
     * Get tablo mode status from associated work sessions
     */
    protected function getTabloModeStatus(): bool
    {
        // Check if album has work sessions loaded and if any of them is in tablo mode
        if ($this->relationLoaded('workSessions')) {
            return $this->workSessions->contains('is_tablo_mode', true);
        }

        // If work sessions are not loaded, query them
        $hasTabloSession = $this->workSessions()->where('is_tablo_mode', true)->exists();

        return $hasTabloSession;
    }

    /**
     * Get digit code enabled status from associated work session
     * Priority: User-specific work session > First work session
     */
    protected function getDigitCodeEnabled(): bool
    {
        // Get current authenticated user
        $user = request()->user();

        // If user is authenticated, get work sessions that belong to the user
        if ($user) {
            // Check loaded work sessions first
            if ($this->relationLoaded('workSessions') && $this->workSessions->isNotEmpty()) {
                $userWorkSession = $this->workSessions->first(function ($session) use ($user) {
                    return $session->users()->where('user_id', $user->id)->exists();
                });

                if ($userWorkSession) {
                    return (bool) $userWorkSession->digit_code_enabled;
                }
            }

            // If not loaded, query directly
            $userWorkSession = $this->workSessions()
                ->whereHas('users', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->first();

            if ($userWorkSession) {
                return (bool) $userWorkSession->digit_code_enabled;
            }
        }

        // Fallback: If no user or user has no work session for this album
        // Return first work session's digit code enabled status
        if ($this->relationLoaded('workSessions') && $this->workSessions->isNotEmpty()) {
            return (bool) $this->workSessions->first()->digit_code_enabled;
        }

        $firstSession = $this->workSessions()->first();

        return $firstSession ? (bool) $firstSession->digit_code_enabled : false;
    }

    /**
     * Get allow invitations status from associated work session
     * Priority: User-specific work session > First work session
     */
    protected function getAllowInvitations(): bool
    {
        // Get current authenticated user
        $user = request()->user();

        // If user is authenticated, get work sessions that belong to the user
        if ($user) {
            // Check loaded work sessions first
            if ($this->relationLoaded('workSessions') && $this->workSessions->isNotEmpty()) {
                $userWorkSession = $this->workSessions->first(function ($session) use ($user) {
                    return $session->users()->where('user_id', $user->id)->exists();
                });

                if ($userWorkSession) {
                    return (bool) $userWorkSession->allow_invitations;
                }
            }

            // If not loaded, query directly
            $userWorkSession = $this->workSessions()
                ->whereHas('users', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->first();

            if ($userWorkSession) {
                return (bool) $userWorkSession->allow_invitations;
            }
        }

        // Fallback: If no user or user has no work session for this album
        // Return first work session's allow invitations status
        if ($this->relationLoaded('workSessions') && $this->workSessions->isNotEmpty()) {
            return (bool) $this->workSessions->first()->allow_invitations;
        }

        $firstSession = $this->workSessions()->first();

        return $firstSession ? (bool) $firstSession->allow_invitations : true;
    }
}

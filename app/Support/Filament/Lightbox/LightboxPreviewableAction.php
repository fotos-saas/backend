<?php

namespace App\Support\Filament\Lightbox;

use App\Models\Photo;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reusable lightbox action for photo preview with navigation
 */
class LightboxPreviewableAction
{
    /**
     * Create a lightbox action for photo preview
     */
    public static function make(): Action
    {
        return Action::make('lightbox_preview')
            ->modalContent(function ($record, $livewire) {
                if (! $record instanceof Photo) {
                    return null;
                }

                // Get the query for photos - handle both Resource and RelationManager contexts
                $query = static::getPhotosQuery($record, $livewire);

                $photos = $query->get();
                $currentIndex = $photos->search(fn ($p) => $p->id === $record->id);
                $prevPhoto = $currentIndex > 0 ? $photos[$currentIndex - 1] : null;
                $nextPhoto = $currentIndex < $photos->count() - 1 ? $photos[$currentIndex + 1] : null;

                // Prepare all photos data as JSON for frontend
                $photosData = $photos->map(function ($photo) {
                    $media = $photo->getFirstMedia('photo');
                    $url = $media?->getUrl('preview');

                    // Add cache buster based on media updated_at timestamp
                    if ($url && $media) {
                        $url .= '?t=' . $media->updated_at->timestamp;
                    }

                    return [
                        'id' => $photo->id,
                        'url' => $url,
                    ];
                })->values()->toArray();

                $media = $record->getFirstMedia('photo');
                $imageUrl = $media?->getUrl('preview');

                // Add cache buster to current image
                if ($imageUrl && $media) {
                    $imageUrl .= '?t=' . $media->updated_at->timestamp;
                }

                return view('components.lightbox-image-with-nav', [
                    'imageUrl' => $imageUrl,
                    'recordId' => $record->id,
                    'prevPhotoId' => $prevPhoto?->id,
                    'nextPhotoId' => $nextPhoto?->id,
                    'currentIndex' => $currentIndex + 1,
                    'totalCount' => $photos->count(),
                    'photosData' => $photosData,
                ]);
            })
            ->modalWidth('7xl')
            ->modalHeading(fn ($record) => 'Kép előnézet - ID: '.$record->id)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Bezárás')
            ->slideOver(false);
    }

    /**
     * Get the appropriate photos query based on context
     *
     * @param  Photo  $record  Current photo record
     * @param  mixed  $livewire  Livewire component (Resource or RelationManager)
     */
    protected static function getPhotosQuery(Photo $record, $livewire): Builder
    {
        // Check if we're in a RelationManager context (Album or User)
        if (method_exists($livewire, 'getOwnerRecord')) {
            $owner = $livewire->getOwnerRecord();

            // Album context
            if ($owner instanceof \App\Models\Album) {
                return Photo::query()
                    ->where('album_id', $owner->id)
                    ->orderBy('id', 'desc');
            }

            // User context
            if ($owner instanceof \App\Models\User) {
                return Photo::query()
                    ->where('assigned_user_id', $owner->id)
                    ->orderBy('id', 'desc');
            }
        }

        // PhotoResource context - respect table filters
        $query = Photo::query()->orderBy('id', 'desc');

        // Apply active filters from the table if available
        if (property_exists($livewire, 'tableFilters') && ! empty($livewire->tableFilters)) {
            $filters = $livewire->tableFilters;

            // Album filter
            if (isset($filters['album_id']['value'])) {
                $query->where('album_id', $filters['album_id']['value']);
            }

            // Assigned user filter
            if (isset($filters['assigned_user_id']['values']) && ! empty($filters['assigned_user_id']['values'])) {
                $query->whereIn('assigned_user_id', $filters['assigned_user_id']['values']);
            }

            // Unassigned filter
            if (isset($filters['unassigned']['isActive']) && $filters['unassigned']['isActive']) {
                $query->whereNull('assigned_user_id');
            }
        }

        return $query;
    }
}

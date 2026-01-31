<?php

namespace App\Http\Resources\Tablo;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Poll Resource
 *
 * Szavazás JSON formátum.
 */
class PollResource extends JsonResource
{
    /**
     * Transform the resource into an array for list view.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'cover_image_url' => $this->cover_image_url,
            'media' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => [
                'id' => $m->id,
                'url' => $m->url,
                'fileName' => $m->file_name,
                'sortOrder' => $m->sort_order,
            ]), []),
            'type' => $this->type,
            'is_active' => $this->is_active,
            'is_multiple_choice' => $this->is_multiple_choice,
            'max_votes_per_guest' => $this->max_votes_per_guest,
            'show_results_before_vote' => $this->show_results_before_vote,
            'use_for_finalization' => $this->use_for_finalization,
            'close_at' => $this->close_at?->toIso8601String(),
            'is_open' => $this->isOpen(),
            'total_votes' => $this->total_votes,
            'unique_voters' => $this->unique_voters_count,
            'options_count' => $this->options->count(),
            'my_votes' => $this->when(
                isset($this->additional['my_votes']),
                fn () => $this->additional['my_votes'],
                []
            ),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * Create resource with guest votes.
     */
    public static function withGuestVotes($poll, array $guestVotes): self
    {
        $resource = new self($poll);
        $resource->additional(['my_votes' => $guestVotes]);

        return $resource;
    }
}

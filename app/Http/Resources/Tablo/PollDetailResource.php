<?php

namespace App\Http\Resources\Tablo;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Poll Detail Resource
 *
 * Szavazás részletes JSON formátum (opciókkal és eredményekkel).
 */
class PollDetailResource extends JsonResource
{
    /** @var array|null Results data */
    protected ?array $results = null;

    /** @var array Guest votes */
    protected array $myVotes = [];

    /** @var bool Can guest vote */
    protected bool $canVote = false;

    /**
     * Transform the resource into an array.
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
            'can_vote' => $this->canVote,
            'my_votes' => $this->myVotes,
            'options' => $this->options->map(fn ($option) => [
                'id' => $option->id,
                'label' => $option->label,
                'description' => $option->description,
                'image_url' => $option->display_image_url,
                'template_id' => $option->tablo_sample_template_id,
                'template_name' => $option->template?->name,
            ]),
            'results' => $this->results,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * Set results data.
     */
    public function withResults(?array $results): self
    {
        $this->results = $results;

        return $this;
    }

    /**
     * Set guest votes and can vote status.
     */
    public function withGuestContext(array $myVotes, bool $canVote): self
    {
        $this->myVotes = $myVotes;
        $this->canVote = $canVote;

        return $this;
    }
}

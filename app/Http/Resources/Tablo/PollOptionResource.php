<?php

namespace App\Http\Resources\Tablo;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Poll Option Resource
 *
 * Szavazási opció JSON formátum.
 */
class PollOptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'description' => $this->description,
            'image_url' => $this->display_image_url,
            'template_id' => $this->tablo_sample_template_id,
            'template_name' => $this->template?->name,
            'votes_count' => $this->when(
                isset($this->votes_count),
                fn () => $this->votes_count
            ),
            'percentage' => $this->when(
                isset($this->additional['percentage']),
                fn () => $this->additional['percentage']
            ),
        ];
    }

    /**
     * Create option with percentage.
     */
    public static function withPercentage($option, float $percentage): self
    {
        $resource = new self($option);
        $resource->additional(['percentage' => $percentage]);

        return $resource;
    }
}

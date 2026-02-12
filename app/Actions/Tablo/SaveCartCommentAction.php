<?php

namespace App\Actions\Tablo;

use App\Models\TabloGallery;
use App\Models\TabloUserProgress;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Kosár megjegyzés mentése a munkafolyamat során.
 */
class SaveCartCommentAction
{
    public function execute(Authenticatable $user, TabloGallery $gallery, ?string $comment): void
    {
        $progress = TabloUserProgress::firstOrCreate(
            [
                'user_id' => $user->id,
                'tablo_gallery_id' => $gallery->id,
            ],
            [
                'current_step' => 'claiming',
                'steps_data' => [],
            ]
        );

        $progress->update(['cart_comment' => $comment]);
    }
}

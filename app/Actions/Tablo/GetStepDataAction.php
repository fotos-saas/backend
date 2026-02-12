<?php

namespace App\Actions\Tablo;

use App\Models\TabloGallery;
use App\Models\TabloUserProgress;
use App\Services\TabloWorkflowService;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Lépés adatok lekérése a munkafolyamathoz.
 */
class GetStepDataAction
{
    private const VALID_STEPS = ['claiming', 'registration', 'retouch', 'tablo', 'completed'];

    public function __construct(
        private TabloWorkflowService $workflowService
    ) {}

    /**
     * @return array{success: bool, error?: string, status?: int, data?: array}
     */
    public function execute(Authenticatable $user, TabloGallery $gallery, ?string $step): array
    {
        if (! $step) {
            $progress = TabloUserProgress::where('user_id', $user->id)
                ->where('tablo_gallery_id', $gallery->id)
                ->first();

            $step = $progress?->current_step ?? 'claiming';
        }

        if (! in_array($step, self::VALID_STEPS)) {
            return ['success' => false, 'error' => 'Érvénytelen lépés', 'status' => 400];
        }

        return [
            'success' => true,
            'data' => $this->workflowService->getStepData($user, $gallery, $step),
        ];
    }
}

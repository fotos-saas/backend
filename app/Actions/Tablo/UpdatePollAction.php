<?php

namespace App\Actions\Tablo;

use App\Models\TabloPoll;
use App\Services\Tablo\PollService;

/**
 * Szavazás frissítése: adatok + média kezelés.
 */
class UpdatePollAction
{
    public function __construct(
        private PollService $pollService
    ) {}

    /**
     * @return TabloPoll
     */
    public function execute(TabloPoll $poll, array $validated, array $deleteMediaIds = [], array $mediaFiles = []): TabloPoll
    {
        $this->pollService->update($poll, $validated);

        // Megjelölt médiák törlése
        if (! empty($deleteMediaIds)) {
            $this->pollService->deleteMediaByIds($poll, $deleteMediaIds);
        }

        // Új média feltöltése
        if (! empty($mediaFiles)) {
            $this->pollService->uploadMediaFiles($poll, $mediaFiles);
        }

        $poll->load('media');

        return $poll;
    }
}

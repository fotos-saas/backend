<?php

namespace App\Actions\Tablo;

use App\Models\TabloPoll;
use App\Models\TabloProject;
use App\Services\Tablo\PollService;
use Illuminate\Http\UploadedFile;

/**
 * Szavazás létrehozása + média feltöltés.
 */
class CreatePollAction
{
    public function __construct(
        private PollService $pollService
    ) {}

    /**
     * @return array{success: bool, error?: string, poll?: TabloPoll}
     */
    public function execute(
        TabloProject $project,
        array $validated,
        ?int $contactId,
        ?UploadedFile $coverImage,
        array $mediaFiles = []
    ): array {
        // Ellenőrzés: osztálylétszám beállítva
        if (! $this->pollService->canCreatePoll($project)) {
            return [
                'success' => false,
                'error' => 'Először állítsd be az osztálylétszámot!',
                'requires_class_size' => true,
            ];
        }

        $poll = $this->pollService->create(
            $project,
            $validated,
            $contactId,
            $coverImage
        );

        // Média fájlok feltöltése (max 5)
        if (! empty($mediaFiles)) {
            $this->pollService->uploadMediaFiles($poll, $mediaFiles);
        }

        $poll->load('media');

        return [
            'success' => true,
            'poll' => $poll,
        ];
    }
}

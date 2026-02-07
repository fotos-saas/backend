<?php

namespace App\Actions\Tablo;

use App\Models\TabloGuestSession;
use App\Models\TabloProject;
use App\Services\Tablo\GuestSessionService;

/**
 * Eszköz-link küldése vendégnek emailben.
 *
 * Session keresés, email frissítés, link generálás.
 */
class SendGuestDeviceLinkAction
{
    public function __construct(
        protected GuestSessionService $guestSessionService
    ) {}

    /**
     * @return array{success: bool, message: string, link: string|null, status: int}
     */
    public function execute(TabloProject $project, string $sessionToken, string $email): array
    {
        $session = TabloGuestSession::where('session_token', $sessionToken)
            ->where('tablo_project_id', $project->id)
            ->first();

        if (! $session) {
            return [
                'success' => false,
                'message' => 'Session nem található',
                'link' => null,
                'status' => 404,
            ];
        }

        // Email frissítés ha eltér
        if ($session->guest_email !== $email) {
            $session->update(['guest_email' => $email]);
        }

        $link = $this->guestSessionService->generateDeviceLink($session, $project);

        return [
            'success' => true,
            'message' => 'Link elküldve a megadott email címre!',
            'link' => config('app.debug') ? $link : null,
            'status' => 200,
        ];
    }
}

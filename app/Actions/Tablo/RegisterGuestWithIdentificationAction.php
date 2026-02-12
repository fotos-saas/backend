<?php

namespace App\Actions\Tablo;

use App\Models\TabloProject;
use App\Models\User;
use App\Services\Tablo\GuestSessionService;

class RegisterGuestWithIdentificationAction
{
    public function __construct(
        private GuestSessionService $guestSessionService
    ) {}

    /**
     * Vendég regisztráció személy-azonosítással.
     *
     * @return array{success: bool, data?: array, message?: string, status?: int}
     */
    public function execute(
        TabloProject $project,
        array $validated,
        ?string $ip,
        ?User $authUser = null
    ): array {
        // Person validáció: a projekt persons listájában van-e
        if ($validated['person_id'] ?? null) {
            if (!$project->persons()->where('id', $validated['person_id'])->exists()) {
                return [
                    'success' => false,
                    'message' => 'A kiválasztott személy nem tartozik ehhez a projekthez.',
                    'status' => 422,
                ];
            }
        }

        $result = $this->guestSessionService->registerWithIdentification(
            $project,
            $validated['nickname'],
            $validated['person_id'] ?? null,
            $validated['email'] ?? null,
            $validated['device_identifier'] ?? null,
            $ip
        );

        $session = $result['session'];

        // user_id beállítása a session-ben (auth user -> guest session kötés)
        if ($authUser && !$session->user_id) {
            $session->update(['user_id' => $authUser->id]);
        }

        return [
            'success' => true,
            'data' => [
                'id' => $session->id,
                'session_token' => $session->session_token,
                'guest_name' => $session->guest_name,
                'guest_email' => $session->guest_email,
                'verification_status' => $session->verification_status,
                'is_pending' => $session->isPending(),
                'person_id' => $session->tablo_person_id,
                'person_name' => $session->person?->name,
            ],
            'has_conflict' => $result['has_conflict'],
            'conflict_message' => $result['conflict_message'] ?? null,
        ];
    }
}

<?php

namespace App\Actions\Tablo;

use App\Mail\GuestSessionRestoreMail;
use App\Models\TabloGuestSession;
use App\Models\TabloProject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Vendég session visszaállítási link igénylése.
 *
 * Rate limiting + restore token generálás + email küldés.
 * Biztonsági okokból mindig azonos üzenetet ad vissza,
 * függetlenül attól, hogy létezik-e a fiók.
 */
class RequestRestoreLinkAction
{
    /** Max kérés / óra / email + projekt kombináció */
    private const MAX_ATTEMPTS_PER_HOUR = 3;

    /**
     * @return array{success: bool, message: string, status: int}
     */
    public function execute(int $projectId, string $email): array
    {
        $project = TabloProject::find($projectId);

        if (! $project) {
            // Biztonsági okokból azonos üzenetet adunk vissza
            return $this->safeResponse();
        }

        // Rate limiting ellenőrzés
        $rateLimitKey = "restore_link:{$projectId}:{$email}";
        $attempts = (int) Cache::get($rateLimitKey, 0);

        if ($attempts >= self::MAX_ATTEMPTS_PER_HOUR) {
            return [
                'success' => false,
                'message' => 'Túl sok kérés. Kérjük próbáld újra később.',
                'status' => 429,
            ];
        }

        // Keresés email alapján
        $session = TabloGuestSession::where('tablo_project_id', $projectId)
            ->where('guest_email', $email)
            ->verified()
            ->active()
            ->first();

        if (! $session) {
            // Biztonsági okokból NE mondjuk meg, hogy nem létezik
            Cache::put($rateLimitKey, $attempts + 1, now()->addHour());

            return $this->safeResponse();
        }

        // Rate limit növelése
        Cache::put($rateLimitKey, $attempts + 1, now()->addHour());

        // Restore token generálás
        $restoreToken = Str::random(64);
        $session->update([
            'restore_token' => $restoreToken,
            'restore_token_expires_at' => now()->addHours(24),
        ]);

        // Frontend URL összeállítása
        $frontendUrl = rtrim(config('app.frontend_tablo_url', config('app.url')), '/');
        $restoreLink = "{$frontendUrl}/share/{$project->share_token}?restore={$restoreToken}";

        // Email küldés
        return $this->sendRestoreEmail($session, $project, $restoreLink, $email, $projectId);
    }

    /**
     * Restore email küldése.
     *
     * @return array{success: bool, message: string, status: int}
     */
    private function sendRestoreEmail(
        TabloGuestSession $session,
        TabloProject $project,
        string $restoreLink,
        string $email,
        int $projectId
    ): array {
        try {
            Mail::to($email)->send(
                new GuestSessionRestoreMail($session, $project, $restoreLink)
            );

            Log::info('[GuestSession] Restore link sent', [
                'project_id' => $projectId,
                'session_id' => $session->id,
                'email' => $email,
            ]);
        } catch (\Exception $e) {
            Log::error('[GuestSession] Failed to send restore link', [
                'project_id' => $projectId,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            // Biztonsági okokból azonos üzenetet adunk vissza
            return $this->safeResponse();
        }

        return $this->safeResponse();
    }

    /**
     * Biztonsági szempontból egységes válasz.
     *
     * @return array{success: bool, message: string, status: int}
     */
    private function safeResponse(): array
    {
        return [
            'success' => true,
            'message' => 'Ha létezik fiók ezzel az email címmel, linket küldtünk.',
            'status' => 200,
        ];
    }
}

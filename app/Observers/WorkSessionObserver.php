<?php

namespace App\Observers;

use App\Models\WorkSession;

class WorkSessionObserver
{
    /**
     * Handle the WorkSession "saving" event.
     * Automatically generate digit code and share token when enabled.
     */
    public function saving(WorkSession $session): void
    {
        // Digit code generálás
        if ($session->digit_code_enabled && empty($session->digit_code)) {
            // Generáljunk egyedi kódot (retry mechanizmussal)
            $session->digit_code = $session->generateDigitCode();

            if (empty($session->digit_code_expires_at)) {
                $session->digit_code_expires_at = now()->addDays(30);
            }
        }

        // Ha van digit_code, ellenőrizzük az egyediségét
        if ($session->digit_code_enabled && $session->digit_code) {
            // Ha már létezik ilyen kód (és nem a saját rekordunk), generáljunk újat
            $existingCode = WorkSession::where('digit_code', $session->digit_code)
                ->where('id', '!=', $session->id ?? 0)
                ->exists();

            if ($existingCode) {
                $session->digit_code = $session->generateDigitCode();
            }
        }

        // Digit code törlés ha disabled
        if (! $session->digit_code_enabled) {
            $session->digit_code = null;
            $session->digit_code_expires_at = null;
        }

        // Share token generálás
        if ($session->share_enabled && empty($session->share_token)) {
            $session->share_token = $session->generateShareToken();

            if (empty($session->share_expires_at)) {
                $session->share_expires_at = now()->addDays(7);
            }
        }

        // Share token törlés ha disabled
        if (! $session->share_enabled) {
            $session->share_token = null;
            $session->share_expires_at = null;
        }
    }

    /**
     * Handle the WorkSession "updated" event.
     * Revoke all guest user tokens when digit code is disabled.
     */
    public function updated(WorkSession $session): void
    {
        // Check if digit_code_enabled was changed to false
        if ($session->wasChanged('digit_code_enabled') && !$session->digit_code_enabled) {
            \Log::info('[WorkSessionObserver] digit_code_enabled disabled, revoking tokens', [
                'work_session_id' => $session->id,
                'work_session_name' => $session->name,
            ]);
            // Revoke all tokens associated with this work session
            $session->revokeUserTokens();
        }
    }

    /**
     * Handle the WorkSession "saved" event.
     * This runs after both created() and updated().
     * Revoke tokens if digit_code_enabled is false (belt and suspenders approach).
     */
    public function saved(WorkSession $session): void
    {
        // Additional check: if digit_code is now disabled, revoke tokens
        // This catches any edge cases where wasChanged() might not detect the change
        if (!$session->digit_code_enabled) {
            // Only revoke if there are actually tokens to revoke
            $tokenCount = \Laravel\Sanctum\PersonalAccessToken::where('work_session_id', $session->id)->count();
            if ($tokenCount > 0) {
                \Log::info('[WorkSessionObserver] saved() detected disabled digit_code, revoking tokens', [
                    'work_session_id' => $session->id,
                    'token_count' => $tokenCount,
                ]);
                $session->revokeUserTokens();
            }
        }
    }

    /**
     * Handle the WorkSession "deleting" event.
     * Revoke all guest user tokens when work session is being deleted.
     */
    public function deleting(WorkSession $session): void
    {
        // Revoke all tokens associated with this work session
        $session->revokeUserTokens();
    }
}

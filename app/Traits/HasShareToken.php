<?php

namespace App\Traits;

/**
 * Share token and admin preview token kezelés.
 *
 * Ezt a trait-et a TabloProject model-be kell beilleszteni.
 * A share_token, admin_preview_token, share_token_enabled, share_token_expires_at,
 * admin_preview_token_expires_at mezokre tamaszkodik.
 */
trait HasShareToken
{
    /**
     * Generate unique share token (64 chars, URL-safe)
     */
    public function generateShareToken(): string
    {
        do {
            $token = bin2hex(random_bytes(32));
        } while (static::where('share_token', $token)->exists());

        return $token;
    }

    /**
     * Check if share token is valid (enabled and not expired)
     */
    public function hasValidShareToken(): bool
    {
        if (! $this->share_token_enabled || ! $this->share_token) {
            return false;
        }

        if (! $this->share_token_expires_at) {
            return true; // Vegtelen lejarat
        }

        return $this->share_token_expires_at->isFuture();
    }

    /**
     * Get the public share URL
     */
    public function getShareUrl(): ?string
    {
        if (! $this->share_token) {
            return null;
        }

        return config('app.frontend_tablo_url') . '/share/' . $this->share_token;
    }

    /**
     * Generate one-time admin preview token (expires in 5 minutes)
     *
     * Note: Uses direct property assignment instead of update() because
     * admin_preview_token is guarded for security (prevents mass assignment).
     */
    public function generateAdminPreviewToken(): string
    {
        $token = bin2hex(random_bytes(32));

        $this->admin_preview_token = $token;
        $this->admin_preview_token_expires_at = now()->addMinutes(5);
        $this->save();

        return $token;
    }

    /**
     * Consume admin preview token (invalidate after use)
     *
     * Note: Uses direct property assignment instead of update() because
     * admin_preview_token is guarded for security (prevents mass assignment).
     */
    public function consumeAdminPreviewToken(): void
    {
        $this->admin_preview_token = null;
        $this->admin_preview_token_expires_at = null;
        $this->save();
    }

    /**
     * Check if admin preview token is valid
     */
    public function hasValidAdminPreviewToken(?string $token): bool
    {
        if (! $this->admin_preview_token || ! $token) {
            return false;
        }

        if ($this->admin_preview_token !== $token) {
            return false;
        }

        if ($this->admin_preview_token_expires_at && $this->admin_preview_token_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Get admin preview URL
     */
    public function getAdminPreviewUrl(): string
    {
        $token = $this->generateAdminPreviewToken();

        return config('app.frontend_tablo_url') . '/preview/' . $token;
    }

    /**
     * Find project by share token
     */
    public static function findByShareToken(string $token): ?self
    {
        return static::where('share_token', $token)
            ->where('share_token_enabled', true)
            ->where(function ($q) {
                $q->whereNull('share_token_expires_at')
                    ->orWhere('share_token_expires_at', '>', now());
            })
            ->first();
    }

    /**
     * Find project by admin preview token
     */
    public static function findByAdminPreviewToken(string $token): ?self
    {
        return static::where('admin_preview_token', $token)
            ->where(function ($q) {
                $q->whereNull('admin_preview_token_expires_at')
                    ->orWhere('admin_preview_token_expires_at', '>', now());
            })
            ->first();
    }
}

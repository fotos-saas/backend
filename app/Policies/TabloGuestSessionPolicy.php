<?php

namespace App\Policies;

use App\Models\TabloGuestSession;
use App\Models\User;

/**
 * Authorization policy for TabloGuestSession model.
 *
 * SECURITY: Protects against unauthorized guest session manipulation.
 */
class TabloGuestSessionPolicy
{
    /**
     * Determine whether the user can view any guest sessions.
     */
    public function viewAny(User $user): bool
    {
        return $user->partner_id !== null;
    }

    /**
     * Determine whether the user can view the guest session.
     */
    public function view(User $user, TabloGuestSession $session): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->partner_id
            && $session->tabloProject
            && $session->tabloProject->partner_id === $user->partner_id;
    }

    /**
     * Determine whether the user can update the guest session (ban, promote, etc.).
     */
    public function update(User $user, TabloGuestSession $session): bool
    {
        return $this->view($user, $session);
    }

    /**
     * Determine whether the user can delete the guest session.
     */
    public function delete(User $user, TabloGuestSession $session): bool
    {
        return $this->view($user, $session);
    }
}

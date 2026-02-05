<?php

namespace App\Policies;

use App\Models\PartnerClient;
use App\Models\User;

/**
 * Authorization policy for PartnerClient model.
 *
 * SECURITY: Protects against unauthorized client data access (IDOR attacks).
 */
class PartnerClientPolicy
{
    /**
     * Determine whether the user can view any clients.
     */
    public function viewAny(User $user): bool
    {
        return $user->partner_id !== null || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the client.
     */
    public function view(User $user, PartnerClient $client): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->partner_id
            && $client->tablo_partner_id === $user->partner_id;
    }

    /**
     * Determine whether the user can create clients.
     */
    public function create(User $user): bool
    {
        return $user->partner_id !== null;
    }

    /**
     * Determine whether the user can update the client.
     */
    public function update(User $user, PartnerClient $client): bool
    {
        return $this->view($user, $client);
    }

    /**
     * Determine whether the user can delete the client.
     */
    public function delete(User $user, PartnerClient $client): bool
    {
        return $this->view($user, $client);
    }
}

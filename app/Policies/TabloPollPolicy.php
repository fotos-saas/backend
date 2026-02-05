<?php

namespace App\Policies;

use App\Models\TabloPoll;
use App\Models\User;

/**
 * Authorization policy for TabloPoll model.
 *
 * SECURITY: Protects against unauthorized poll access/modification.
 */
class TabloPollPolicy
{
    /**
     * Determine whether the user can view any polls.
     */
    public function viewAny(User $user): bool
    {
        return $user->partner_id !== null;
    }

    /**
     * Determine whether the user can view the poll.
     */
    public function view(User $user, TabloPoll $poll): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->partner_id
            && $poll->tabloProject
            && $poll->tabloProject->partner_id === $user->partner_id;
    }

    /**
     * Determine whether the user can create polls.
     */
    public function create(User $user): bool
    {
        return $user->partner_id !== null;
    }

    /**
     * Determine whether the user can update the poll.
     */
    public function update(User $user, TabloPoll $poll): bool
    {
        return $this->view($user, $poll);
    }

    /**
     * Determine whether the user can delete the poll.
     */
    public function delete(User $user, TabloPoll $poll): bool
    {
        return $this->view($user, $poll);
    }
}

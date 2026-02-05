<?php

namespace App\Policies;

use App\Models\TabloDiscussion;
use App\Models\User;

/**
 * Authorization policy for TabloDiscussion model.
 *
 * SECURITY: Protects against unauthorized discussion access/modification.
 */
class TabloDiscussionPolicy
{
    /**
     * Determine whether the user can view any discussions.
     */
    public function viewAny(User $user): bool
    {
        return $user->partner_id !== null;
    }

    /**
     * Determine whether the user can view the discussion.
     */
    public function view(User $user, TabloDiscussion $discussion): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->partner_id
            && $discussion->tabloProject
            && $discussion->tabloProject->partner_id === $user->partner_id;
    }

    /**
     * Determine whether the user can create discussions.
     */
    public function create(User $user): bool
    {
        return $user->partner_id !== null;
    }

    /**
     * Determine whether the user can update the discussion.
     */
    public function update(User $user, TabloDiscussion $discussion): bool
    {
        return $this->view($user, $discussion);
    }

    /**
     * Determine whether the user can delete the discussion.
     */
    public function delete(User $user, TabloDiscussion $discussion): bool
    {
        return $this->view($user, $discussion);
    }

    /**
     * Determine whether the user can lock/pin the discussion.
     */
    public function moderate(User $user, TabloDiscussion $discussion): bool
    {
        return $this->view($user, $discussion);
    }
}

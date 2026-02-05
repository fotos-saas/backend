<?php

namespace App\Policies;

use App\Models\TabloPollVote;
use App\Models\User;

/**
 * Authorization policy for TabloPollVote model.
 *
 * SECURITY: Protects against unauthorized vote manipulation.
 */
class TabloPollVotePolicy
{
    /**
     * Determine whether the user can view the vote.
     */
    public function view(User $user, TabloPollVote $vote): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $poll = $vote->poll;

        return $user->partner_id
            && $poll
            && $poll->tabloProject
            && $poll->tabloProject->partner_id === $user->partner_id;
    }

    /**
     * Determine whether the user can delete the vote.
     */
    public function delete(User $user, TabloPollVote $vote): bool
    {
        return $this->view($user, $vote);
    }
}

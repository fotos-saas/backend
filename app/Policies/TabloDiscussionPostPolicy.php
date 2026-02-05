<?php

namespace App\Policies;

use App\Models\TabloDiscussionPost;
use App\Models\User;

/**
 * Authorization policy for TabloDiscussionPost model.
 *
 * SECURITY: Protects against unauthorized discussion post manipulation.
 */
class TabloDiscussionPostPolicy
{
    /**
     * Determine whether the user can view the post.
     */
    public function view(User $user, TabloDiscussionPost $post): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $discussion = $post->discussion;

        return $user->partner_id
            && $discussion
            && $discussion->tabloProject
            && $discussion->tabloProject->partner_id === $user->partner_id;
    }

    /**
     * Determine whether the user can update the post.
     */
    public function update(User $user, TabloDiscussionPost $post): bool
    {
        return $this->view($user, $post);
    }

    /**
     * Determine whether the user can delete the post.
     */
    public function delete(User $user, TabloDiscussionPost $post): bool
    {
        return $this->view($user, $post);
    }
}

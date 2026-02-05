<?php

namespace App\Policies;

use App\Models\TabloNewsfeedPost;
use App\Models\User;

/**
 * Authorization policy for TabloNewsfeedPost model.
 *
 * SECURITY: Protects against unauthorized newsfeed post access/modification.
 */
class TabloNewsfeedPostPolicy
{
    /**
     * Determine whether the user can view any newsfeed posts.
     */
    public function viewAny(User $user): bool
    {
        return $user->partner_id !== null;
    }

    /**
     * Determine whether the user can view the newsfeed post.
     */
    public function view(User $user, TabloNewsfeedPost $post): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->partner_id
            && $post->tabloProject
            && $post->tabloProject->partner_id === $user->partner_id;
    }

    /**
     * Determine whether the user can create newsfeed posts.
     */
    public function create(User $user): bool
    {
        return $user->partner_id !== null;
    }

    /**
     * Determine whether the user can update the newsfeed post.
     */
    public function update(User $user, TabloNewsfeedPost $post): bool
    {
        return $this->view($user, $post);
    }

    /**
     * Determine whether the user can delete the newsfeed post.
     */
    public function delete(User $user, TabloNewsfeedPost $post): bool
    {
        return $this->view($user, $post);
    }
}

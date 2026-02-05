<?php

namespace App\Policies;

use App\Models\TabloNewsfeedComment;
use App\Models\User;

/**
 * Authorization policy for TabloNewsfeedComment model.
 *
 * SECURITY: Protects against unauthorized newsfeed comment manipulation.
 */
class TabloNewsfeedCommentPolicy
{
    /**
     * Determine whether the user can view the comment.
     */
    public function view(User $user, TabloNewsfeedComment $comment): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $post = $comment->newsfeedPost;

        return $user->partner_id
            && $post
            && $post->tabloProject
            && $post->tabloProject->partner_id === $user->partner_id;
    }

    /**
     * Determine whether the user can update the comment.
     */
    public function update(User $user, TabloNewsfeedComment $comment): bool
    {
        return $this->view($user, $comment);
    }

    /**
     * Determine whether the user can delete the comment.
     */
    public function delete(User $user, TabloNewsfeedComment $comment): bool
    {
        return $this->view($user, $comment);
    }
}

<?php

namespace App\Policies;

use App\Models\EmailTemplate;
use App\Models\User;

/**
 * Authorization policy for EmailTemplate model.
 *
 * SECURITY: Only admin users can manage email templates.
 */
class EmailTemplatePolicy
{
    /**
     * Determine whether the user can view any email templates.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the email template.
     */
    public function view(User $user, EmailTemplate $template): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can create email templates.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the email template.
     */
    public function update(User $user, EmailTemplate $template): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the email template.
     */
    public function delete(User $user, EmailTemplate $template): bool
    {
        return $user->hasRole('admin');
    }
}

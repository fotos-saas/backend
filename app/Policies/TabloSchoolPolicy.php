<?php

namespace App\Policies;

use App\Models\TabloSchool;
use App\Models\User;

/**
 * Authorization policy for TabloSchool model.
 *
 * SECURITY: Protects against unauthorized school data access.
 */
class TabloSchoolPolicy
{
    /**
     * Determine whether the user can view any schools.
     */
    public function viewAny(User $user): bool
    {
        return $user->partner_id !== null || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the school.
     */
    public function view(User $user, TabloSchool $school): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (!$user->partner_id) {
            return false;
        }

        // Partner can view schools linked to their partner account
        return $school->partners()->where('tablo_partners.id', $user->partner_id)->exists();
    }

    /**
     * Determine whether the user can create schools.
     */
    public function create(User $user): bool
    {
        return $user->partner_id !== null || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the school.
     */
    public function update(User $user, TabloSchool $school): bool
    {
        return $this->view($user, $school);
    }

    /**
     * Determine whether the user can delete the school.
     */
    public function delete(User $user, TabloSchool $school): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $this->view($user, $school);
    }
}

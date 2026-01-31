<?php

namespace App\Policies;

use App\Models\TabloContact;
use App\Models\User;

class TabloContactPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('tablo-projects.relation.contacts');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TabloContact $contact): bool
    {
        return $user->can('tablo-projects.relation.contacts');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('tablo-projects.relation.contacts');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TabloContact $contact): bool
    {
        return $user->can('tablo-projects.relation.contacts');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TabloContact $contact): bool
    {
        return $user->can('tablo-projects.relation.contacts');
    }
}

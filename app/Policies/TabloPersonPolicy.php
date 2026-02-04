<?php

namespace App\Policies;

use App\Models\TabloPerson;
use App\Models\User;

class TabloPersonPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('tablo-projects.relation.persons');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TabloPerson $person): bool
    {
        return $user->can('tablo-projects.relation.persons');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('tablo-projects.relation.persons');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TabloPerson $person): bool
    {
        return $user->can('tablo-projects.relation.persons');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TabloPerson $person): bool
    {
        return $user->can('tablo-projects.relation.persons');
    }
}

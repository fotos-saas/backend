<?php

namespace App\Policies;

use App\Models\TabloMissingPerson;
use App\Models\User;

class TabloMissingPersonPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('tablo-projects.relation.missing-persons');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TabloMissingPerson $missingPerson): bool
    {
        return $user->can('tablo-projects.relation.missing-persons');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('tablo-projects.relation.missing-persons');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TabloMissingPerson $missingPerson): bool
    {
        return $user->can('tablo-projects.relation.missing-persons');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TabloMissingPerson $missingPerson): bool
    {
        return $user->can('tablo-projects.relation.missing-persons');
    }
}

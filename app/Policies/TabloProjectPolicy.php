<?php

namespace App\Policies;

use App\Models\TabloProject;
use App\Models\User;

class TabloProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('tablo-projects.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TabloProject $project): bool
    {
        return $user->can('tablo-projects.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('tablo-projects.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TabloProject $project): bool
    {
        return $user->can('tablo-projects.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TabloProject $project): bool
    {
        return $user->can('tablo-projects.delete');
    }
}

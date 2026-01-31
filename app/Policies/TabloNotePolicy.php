<?php

namespace App\Policies;

use App\Models\TabloNote;
use App\Models\User;

class TabloNotePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('tablo-projects.relation.notes');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TabloNote $note): bool
    {
        return $user->can('tablo-projects.relation.notes');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('tablo-projects.relation.notes');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TabloNote $note): bool
    {
        return $user->can('tablo-projects.relation.notes');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TabloNote $note): bool
    {
        return $user->can('tablo-projects.relation.notes');
    }
}

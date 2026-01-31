<?php

namespace App\Policies;

use App\Models\TabloPartner;
use App\Models\User;

class TabloPartnerPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('tablo_partner.view_any');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TabloPartner $partner): bool
    {
        return $user->can('tablo_partner.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('tablo_partner.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TabloPartner $partner): bool
    {
        return $user->can('tablo_partner.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TabloPartner $partner): bool
    {
        return $user->can('tablo_partner.delete');
    }
}

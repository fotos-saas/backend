<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ShopOrder;
use App\Models\User;

class ShopOrderPolicy
{
    public function viewAny(User $user): bool
    {
        $partner = $user->tabloPartner;

        return $partner !== null;
    }

    public function view(User $user, ShopOrder $order): bool
    {
        $partner = $user->tabloPartner;
        if (!$partner) {
            return false;
        }

        return $order->tablo_partner_id === $partner->id;
    }

    public function update(User $user, ShopOrder $order): bool
    {
        return $this->view($user, $order);
    }
}

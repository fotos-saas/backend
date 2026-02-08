<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TabloInvoice;
use App\Models\User;

class TabloInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tablo_partner_id !== null;
    }

    public function view(User $user, TabloInvoice $invoice): bool
    {
        return $user->tablo_partner_id === $invoice->tablo_partner_id;
    }

    public function create(User $user): bool
    {
        return $user->tablo_partner_id !== null;
    }

    public function update(User $user, TabloInvoice $invoice): bool
    {
        return $user->tablo_partner_id === $invoice->tablo_partner_id;
    }

    public function delete(User $user, TabloInvoice $invoice): bool
    {
        return $user->tablo_partner_id === $invoice->tablo_partner_id;
    }
}

<?php

namespace App\Policies;

use App\Models\BugReport;
use App\Models\User;

class BugReportPolicy
{
    /**
     * Partner, marketer, csapattagok listázhatják a sajátjukat.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            User::ROLE_SUPER_ADMIN,
            'partner',
            User::ROLE_MARKETER,
            User::ROLE_DESIGNER,
            User::ROLE_PRINTER,
            User::ROLE_ASSISTANT,
        ]);
    }

    /**
     * SuperAdmin mindent lát, bejelentő a sajátját.
     */
    public function view(User $user, BugReport $bugReport): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $bugReport->reporter_id === $user->id;
    }

    /**
     * Partner, marketer, csapattagok hozhatnak létre.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            'partner',
            User::ROLE_MARKETER,
            User::ROLE_DESIGNER,
            User::ROLE_PRINTER,
            User::ROLE_ASSISTANT,
        ]);
    }

    /**
     * Csak SuperAdmin módosíthat.
     */
    public function update(User $user, BugReport $bugReport): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Csak SuperAdmin törölhet.
     */
    public function delete(User $user, BugReport $bugReport): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Státusz módosítás - csak SuperAdmin.
     */
    public function updateStatus(User $user, BugReport $bugReport): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Komment hozzáadása - SuperAdmin vagy bejelentő.
     */
    public function addComment(User $user, BugReport $bugReport): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $bugReport->reporter_id === $user->id;
    }
}

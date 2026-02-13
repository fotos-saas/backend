<?php

namespace App\Policies;

use App\Models\StudentArchive;
use App\Models\User;

class StudentArchivePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->partner_id !== null || $user->hasRole('admin');
    }

    public function view(User $user, StudentArchive $student): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (!$user->partner_id) {
            return false;
        }

        return $student->partner_id === $user->partner_id;
    }

    public function create(User $user): bool
    {
        return $user->partner_id !== null || $user->hasRole('admin');
    }

    public function update(User $user, StudentArchive $student): bool
    {
        return $this->view($user, $student);
    }

    public function delete(User $user, StudentArchive $student): bool
    {
        return $this->view($user, $student);
    }
}

<?php

namespace App\Policies;

use App\Models\TeacherArchive;
use App\Models\User;

class TeacherArchivePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->partner_id !== null || $user->hasRole('admin');
    }

    public function view(User $user, TeacherArchive $teacher): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (!$user->partner_id) {
            return false;
        }

        return $teacher->partner_id === $user->partner_id;
    }

    public function create(User $user): bool
    {
        return $user->partner_id !== null || $user->hasRole('admin');
    }

    public function update(User $user, TeacherArchive $teacher): bool
    {
        return $this->view($user, $teacher);
    }

    public function delete(User $user, TeacherArchive $teacher): bool
    {
        return $this->view($user, $teacher);
    }
}

<?php

namespace App\Policies;

use App\Models\WorkSession;
use App\Models\User;

/**
 * Authorization policy for WorkSession model.
 *
 * SECURITY: Protects against unauthorized work session access (IDOR attacks).
 */
class WorkSessionPolicy
{
    /**
     * Determine whether the user can view any work sessions.
     */
    public function viewAny(?User $user): bool
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can view the work session.
     *
     * Access is granted if:
     * 1. User owns the work session (user_id match)
     * 2. User is a partner who owns the work session (partner_id match)
     * 3. User has valid access code token for this session
     */
    public function view(?User $user, WorkSession $workSession): bool
    {
        // 1. Direct ownership
        if ($user && $workSession->user_id === $user->id) {
            return true;
        }

        // 2. Partner ownership
        if ($user && $user->partner_id && $workSession->partner_id === $user->partner_id) {
            return true;
        }

        // 3. Admin access
        if ($user && $user->hasRole('admin')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create work sessions.
     */
    public function create(?User $user): bool
    {
        // Only partners can create work sessions
        return $user && $user->partner_id;
    }

    /**
     * Determine whether the user can update the work session.
     */
    public function update(?User $user, WorkSession $workSession): bool
    {
        if (!$user) {
            return false;
        }

        // Only partner who owns the session can update
        if ($user->partner_id && $workSession->partner_id === $user->partner_id) {
            return true;
        }

        // Admin can update any
        if ($user->hasRole('admin')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the work session.
     */
    public function delete(?User $user, WorkSession $workSession): bool
    {
        return $this->update($user, $workSession);
    }

    /**
     * Determine whether the user can send emails for the work session.
     */
    public function sendEmail(?User $user, WorkSession $workSession): bool
    {
        return $this->update($user, $workSession);
    }

    /**
     * Determine whether the user can download the work session ZIP.
     */
    public function downloadZip(?User $user, WorkSession $workSession): bool
    {
        return $this->view($user, $workSession);
    }
}

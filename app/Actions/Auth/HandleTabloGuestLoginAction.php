<?php

namespace App\Actions\Auth;

use App\Models\TabloGuestSession;
use App\Models\TabloPartner;
use App\Models\TabloProject;
use App\Models\User;

class HandleTabloGuestLoginAction
{
    /**
     * Tablo-guest user login kiegészítése (internal tablo email alapján).
     */
    public function execute(User $user, array $response): array
    {
        if (!preg_match('/^tablo-guest-(\d+)-[a-zA-Z0-9]+@internal\.local$/', $user->email ?? '', $matches)) {
            return $response;
        }

        $projectId = (int) $matches[1];
        $project = TabloProject::find($projectId);

        if (!$project) {
            return $response;
        }

        // Keresés: először user_id alapján, fallback guest_name-re
        $guestSession = TabloGuestSession::where('user_id', $user->id)
            ->where('tablo_project_id', $projectId)
            ->first()
            ?? TabloGuestSession::where('guest_name', $user->name)
                ->where('tablo_project_id', $projectId)
                ->latest('last_activity_at')
                ->first();

        // user_id beállítása ha még nincs
        if ($guestSession && !$guestSession->user_id) {
            $guestSession->update(['user_id' => $user->id]);
        }

        $response['user']['type'] = 'tablo-guest';
        $projectData = [
            'id' => $project->id,
            'name' => $project->display_name,
            'schoolName' => $project->school?->name,
            'className' => $project->class_name,
            'classYear' => $project->class_year,
            'samplesCount' => $project->getMedia('samples')->count(),
            'activePollsCount' => $project->polls()->active()->count(),
            'contacts' => [],
        ];

        TabloPartner::appendBranding($projectData, $project->partner);

        $response['project'] = $projectData;
        $response['tokenType'] = 'code';
        $response['canFinalize'] = true;

        if ($guestSession) {
            $response['guestSession'] = [
                'sessionToken' => $guestSession->session_token,
                'guestName' => $guestSession->guest_name,
            ];
        }

        return $response;
    }
}

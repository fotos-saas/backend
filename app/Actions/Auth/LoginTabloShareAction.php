<?php

namespace App\Actions\Auth;

use App\Enums\TabloProjectStatus;
use App\Models\TabloGuestSession;
use App\Models\TabloProject;
use App\Models\TabloProjectAccessLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LoginTabloShareAction
{
    public function execute(Request $request): JsonResponse
    {
        $token = $request->input('token');
        $restoreToken = $request->input('restore');

        $tabloProject = TabloProject::findByShareToken($token);

        if (! $tabloProject) {
            return response()->json([
                'message' => 'Érvénytelen vagy lejárt megosztási link',
            ], 401);
        }

        if (in_array($tabloProject->status, [TabloProjectStatus::Done, TabloProjectStatus::InPrint])) {
            return response()->json([
                'message' => 'Ez a projekt már lezárult',
            ], 401);
        }

        $restoredSession = null;
        if ($restoreToken) {
            $restoredSession = TabloGuestSession::where('restore_token', $restoreToken)
                ->where('tablo_project_id', $tabloProject->id)
                ->where('restore_token_expires_at', '>', now())
                ->verified()
                ->active()
                ->first();

            if ($restoredSession) {
                $restoredSession->update([
                    'restore_token' => null,
                    'restore_token_expires_at' => null,
                    'last_activity_at' => now(),
                ]);
            }
        }

        TabloProjectAccessLog::logAccess(
            $tabloProject->id,
            $restoredSession ? 'restore_link' : 'share_token',
            $request->ip(),
            $request->userAgent(),
            $restoredSession ? ['restored_session_id' => $restoredSession->id] : null
        );

        $guestEmail = 'tablo-guest-' . $tabloProject->id . '-' . Str::random(8) . '@internal.local';

        $tabloGuestUser = User::create([
            'email' => $guestEmail,
            'name' => $restoredSession ? $restoredSession->guest_name : ('Tablo Guest - ' . $tabloProject->display_name),
            'password' => null,
        ]);

        $tabloGuestUser->assignRole(User::ROLE_GUEST);

        $tokenResult = $tabloGuestUser->createToken('tablo-share-token');
        $tokenResult->accessToken->tablo_project_id = $tabloProject->id;
        $tokenResult->accessToken->save();
        $authToken = $tokenResult->plainTextToken;

        $projectData = [
            'id' => $tabloProject->id,
            'name' => $tabloProject->display_name,
            'schoolName' => $tabloProject->school?->name,
            'className' => $tabloProject->class_name,
            'classYear' => $tabloProject->class_year,
            'samplesCount' => $tabloProject->getMedia('samples')->count(),
            'activePollsCount' => $tabloProject->polls()->active()->count(),
        ];

        if ($tabloProject->partner) {
            $branding = $tabloProject->partner->getActiveBranding();
            if ($branding) {
                $projectData['branding'] = $branding;
            }
        }

        $response = [
            'user' => [
                'id' => $tabloGuestUser->id,
                'name' => $tabloGuestUser->name,
                'email' => $tabloGuestUser->email,
                'type' => 'tablo-guest',
                'passwordSet' => true,
            ],
            'project' => $projectData,
            'token' => $authToken,
            'tokenType' => 'share',
            'canFinalize' => false,
        ];

        if ($restoredSession) {
            $response['restoredSession'] = [
                'sessionToken' => $restoredSession->session_token,
                'guestName' => $restoredSession->guest_name,
                'guestEmail' => $restoredSession->guest_email,
            ];
        }

        return response()->json($response);
    }
}

<?php

namespace App\Actions\Auth;

use App\Models\TabloProject;
use App\Models\TabloProjectAccessLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LoginTabloPreviewAction
{
    public function execute(Request $request): JsonResponse
    {
        $token = $request->input('token');
        $tabloProject = TabloProject::findByAdminPreviewToken($token);

        if (! $tabloProject) {
            return response()->json([
                'message' => 'Érvénytelen vagy lejárt előnézeti link',
            ], 401);
        }

        $tabloProject->consumeAdminPreviewToken();

        TabloProjectAccessLog::logAccess(
            $tabloProject->id,
            'admin_preview',
            $request->ip(),
            $request->userAgent(),
            ['one_time' => true]
        );

        $guestEmail = 'tablo-guest-' . $tabloProject->id . '-' . Str::random(8) . '@internal.local';

        $tabloGuestUser = User::create([
            'email' => $guestEmail,
            'name' => 'Tablo Guest - ' . $tabloProject->display_name,
            'password' => null,
        ]);

        $tabloGuestUser->assignRole(User::ROLE_GUEST);

        $tokenResult = $tabloGuestUser->createToken('tablo-preview-token');
        $tokenResult->accessToken->tablo_project_id = $tabloProject->id;
        $tokenResult->accessToken->save();
        $authToken = $tokenResult->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $tabloGuestUser->id,
                'name' => $tabloGuestUser->name,
                'email' => $tabloGuestUser->email,
                'type' => 'tablo-guest',
                'passwordSet' => true,
            ],
            'project' => [
                'id' => $tabloProject->id,
                'name' => $tabloProject->display_name,
                'schoolName' => $tabloProject->school?->name,
                'className' => $tabloProject->class_name,
                'classYear' => $tabloProject->class_year,
                'samplesCount' => $tabloProject->getMedia('samples')->count(),
                'activePollsCount' => $tabloProject->polls()->active()->count(),
            ],
            'token' => $authToken,
            'isPreview' => true,
            'tokenType' => 'preview',
            'canFinalize' => false,
        ]);
    }
}

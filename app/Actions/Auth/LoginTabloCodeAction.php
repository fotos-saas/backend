<?php

namespace App\Actions\Auth;

use App\Enums\TabloProjectStatus;
use App\Models\PartnerClient;
use App\Models\TabloGuestSession;
use App\Models\TabloProject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoginTabloCodeAction
{
    public function execute(string $code, Request $request): JsonResponse
    {
        $tabloProject = TabloProject::where('access_code', $code)->first();

        if (!$tabloProject) {
            return $this->loginAsPartnerClient($code, $request);
        }

        if (! $tabloProject->access_code_enabled) {
            return response()->json([
                'message' => 'A belépési kód le van tiltva',
            ], 401);
        }

        if ($tabloProject->access_code_expires_at && $tabloProject->access_code_expires_at->isPast()) {
            return response()->json([
                'message' => 'A belépési kód lejárt',
            ], 401);
        }

        if (in_array($tabloProject->status, [TabloProjectStatus::Done, TabloProjectStatus::InPrint])) {
            return response()->json([
                'message' => 'Ez a projekt már lezárult',
            ], 401);
        }

        $primaryContact = $tabloProject->contacts()->wherePivot('is_primary', true)->first()
            ?? $tabloProject->contacts()->first();

        $guestEmail = 'tablo-guest-' . $tabloProject->id . '-' . Str::random(8) . '@internal.local';

        $tabloGuestUser = User::create([
            'email' => $guestEmail,
            'name' => $primaryContact?->name ?? ('Kapcsolattartó - ' . $tabloProject->display_name),
            'password' => null,
        ]);

        $tabloGuestUser->assignRole(User::ROLE_GUEST);

        $tokenResult = $tabloGuestUser->createToken('tablo-auth-token');
        $tokenResult->accessToken->tablo_project_id = $tabloProject->id;
        $tokenResult->accessToken->contact_id = $primaryContact?->id;
        $tokenResult->accessToken->save();
        $token = $tokenResult->plainTextToken;

        $guestSession = TabloGuestSession::firstOrCreate(
            [
                'tablo_project_id' => $tabloProject->id,
                'guest_email' => $primaryContact?->email,
            ],
            [
                'session_token' => Str::uuid()->toString(),
                'guest_name' => $primaryContact?->name ?? ('Kapcsolattartó - ' . $tabloProject->display_name),
                'device_identifier' => $request->header('User-Agent', 'unknown'),
                'ip_address' => $request->ip(),
                'last_activity_at' => now(),
                'is_coordinator' => true,
            ]
        );

        if (!$guestSession->wasRecentlyCreated) {
            $guestSession->update([
                'last_activity_at' => now(),
                'is_coordinator' => true,
            ]);
        }

        $projectData = [
            'id' => $tabloProject->id,
            'name' => $tabloProject->display_name,
            'schoolName' => $tabloProject->school?->name,
            'className' => $tabloProject->class_name,
            'classYear' => $tabloProject->class_year,
            'samplesCount' => $tabloProject->getMedia('samples')->count(),
            'activePollsCount' => $tabloProject->polls()->active()->count(),
            'contacts' => $primaryContact ? [[
                'id' => $primaryContact->id,
                'name' => $primaryContact->name,
                'email' => $primaryContact->email,
                'phone' => $primaryContact->phone,
            ]] : [],
        ];

        if ($tabloProject->partner) {
            $branding = $tabloProject->partner->getActiveBranding();
            if ($branding) {
                $projectData['branding'] = $branding;
            }
        }

        return response()->json([
            'user' => [
                'id' => $tabloGuestUser->id,
                'name' => $tabloGuestUser->name,
                'email' => $tabloGuestUser->email,
                'type' => 'tablo-guest',
                'passwordSet' => (bool) $tabloGuestUser->password_set,
            ],
            'project' => $projectData,
            'token' => $token,
            'tokenType' => 'code',
            'canFinalize' => true,
            'guestSession' => [
                'sessionToken' => $guestSession->session_token,
                'guestName' => $guestSession->guest_name,
            ],
            'loginType' => 'tablo',
        ]);
    }

    private function loginAsPartnerClient(string $code, Request $request): JsonResponse
    {
        $client = PartnerClient::byAccessCode($code)->first();

        if (!$client) {
            return response()->json([
                'message' => 'Érvénytelen belépési kód',
            ], 401);
        }

        if ($client->is_registered) {
            return response()->json([
                'message' => 'Ez a fiók már regisztrálva van. Kérlek használd az email/jelszó bejelentkezést!',
                'requiresPasswordLogin' => true,
                'email' => $client->email,
            ], 401);
        }

        if (!$client->canLoginWithCode()) {
            return response()->json([
                'message' => 'A belépési kód le van tiltva vagy lejárt.',
            ], 401);
        }

        if (!$client->partner || !$client->partner->hasFeature('client_orders')) {
            return response()->json([
                'message' => 'A funkció nem elérhető',
            ], 403);
        }

        $client->recordLogin();

        $plainTextToken = Str::random(64);
        $hashedToken = hash('sha256', $plainTextToken);

        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => PartnerClient::class,
            'tokenable_id' => $client->id,
            'name' => 'client-auth-token',
            'token' => $hashedToken,
            'abilities' => json_encode(['client']),
            'partner_client_id' => $client->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $albums = $client->albums()
            ->where('status', '!=', 'draft')
            ->latest()
            ->get()
            ->map(fn ($album) => [
                'id' => $album->id,
                'name' => $album->name,
                'type' => $album->type,
                'status' => $album->status,
                'photosCount' => $album->photos_count,
                'maxSelections' => $album->max_selections,
                'minSelections' => $album->min_selections,
                'isCompleted' => $album->isCompleted(),
            ]);

        $canRegister = $client->hasAlbumWithRegistrationAllowed();

        return response()->json([
            'user' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'type' => 'partner-client',
                'isRegistered' => false,
            ],
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'canRegister' => $canRegister,
            ],
            'albums' => $albums,
            'token' => $plainTextToken,
            'tokenType' => 'client',
            'loginType' => 'client',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Dev;

use App\Constants\TokenNames;
use App\Http\Controllers\Api\Concerns\ResolvesPartner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DevLoginRequest;
use App\Models\PartnerClient;
use App\Models\TabloGuestSession;
use App\Models\TabloProject;
use App\Models\User;
use App\Services\AuthenticationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DevLoginController extends Controller
{
    use ResolvesPartner;

    public function __construct(
        private AuthenticationService $authService
    ) {}

    /**
     * Generate a one-time dev login token
     */
    public function generate(DevLoginRequest $request)
    {
        $userType = $request->input('user_type');
        $identifier = (int) $request->input('identifier');

        // Verify entity exists
        $entity = $this->findEntity($userType, $identifier);
        if (!$entity) {
            return response()->json([
                'message' => "Nem található {$userType} #{$identifier}",
            ], 404);
        }

        $token = Str::random(64);

        Cache::put("dev-login:{$token}", [
            'user_type' => $userType,
            'identifier' => $identifier,
        ], now()->addMinutes(5));

        // Frontend URL a request origin-jéből (localhost vs szerver)
        $origin = $request->header('Origin', $request->header('Referer', ''));
        $frontendUrl = preg_replace('#/+$#', '', parse_url($origin, PHP_URL_SCHEME) . '://' . parse_url($origin, PHP_URL_HOST) . (parse_url($origin, PHP_URL_PORT) ? ':' . parse_url($origin, PHP_URL_PORT) : ''));
        if (!$frontendUrl || $frontendUrl === '://') {
            $frontendUrl = config('app.frontend_url', 'http://localhost:4205');
        }

        return response()->json([
            'url' => "{$frontendUrl}/dev-login/{$token}",
            'token' => $token,
            'expiresIn' => 300,
        ]);
    }

    /**
     * Consume a one-time dev login token and return auth data
     */
    public function consume(string $token)
    {
        $data = Cache::pull("dev-login:{$token}");

        if (!$data) {
            return response()->json([
                'message' => 'Érvénytelen vagy lejárt dev login token',
            ], 401);
        }

        $userType = $data['user_type'];
        $identifier = $data['identifier'];

        return match ($userType) {
            'partner', 'marketer', 'designer', 'printer', 'assistant' => $this->loginAsTeamMember($identifier, $userType),
            'tablo-guest' => $this->loginAsTabloGuest($identifier),
            'partner-client' => $this->loginAsPartnerClient($identifier),
        };
    }

    /**
     * Login as partner/team member - same response as LoginController::login()
     */
    private function loginAsTeamMember(int $userId, string $role)
    {
        $user = User::findOrFail($userId);

        $token = $this->authService->createTokenWithMetadata(
            user: $user,
            name: 'dev-login-token',
            loginMethod: 'dev-login',
            ipAddress: '127.0.0.1',
        );

        $roles = $user->getRoleNames()->toArray();
        $partnerRoles = ['partner', 'designer', 'marketer', 'printer', 'assistant'];
        $hasPartnerRole = !empty(array_intersect($roles, $partnerRoles));
        $partner = $hasPartnerRole ? $this->resolvePartner($user->id) : null;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'type' => $user->isMarketer() ? 'marketer' : 'registered',
                'roles' => $roles,
                'passwordSet' => true,
                'has_partner' => (bool) $partner,
                'partner_id' => $partner?->id,
            ],
            'token' => $token,
            'loginType' => 'team',
        ]);
    }

    /**
     * Login as tablo guest - same response as LoginTabloCodeAction::execute()
     */
    private function loginAsTabloGuest(int $sessionId)
    {
        $guestSession = TabloGuestSession::findOrFail($sessionId);
        $project = $guestSession->project;

        $guestEmail = 'tablo-guest-' . $project->id . '-' . Str::random(8) . '@internal.local';

        $tabloGuestUser = User::create([
            'email' => $guestEmail,
            'name' => $guestSession->guest_name,
            'password' => null,
            'password_set' => true,
        ]);

        $tabloGuestUser->assignRole(User::ROLE_GUEST);

        $tokenResult = $tabloGuestUser->createToken(TokenNames::DEV_TABLO);
        $tokenResult->accessToken->tablo_project_id = $project->id;
        $tokenResult->accessToken->save();
        $token = $tokenResult->plainTextToken;

        $primaryContact = $project->contacts()->wherePivot('is_primary', true)->first()
            ?? $project->contacts()->first();

        return response()->json([
            'user' => [
                'id' => $tabloGuestUser->id,
                'name' => $tabloGuestUser->name,
                'email' => $tabloGuestUser->email,
                'type' => 'tablo-guest',
                'passwordSet' => true,
            ],
            'project' => [
                'id' => $project->id,
                'name' => $project->display_name,
                'schoolName' => $project->school?->name,
                'className' => $project->class_name,
                'classYear' => $project->class_year,
                'samplesCount' => $project->getMedia('samples')->count(),
                'activePollsCount' => $project->polls()->active()->count(),
                'contacts' => $primaryContact ? [[
                    'id' => $primaryContact->id,
                    'name' => $primaryContact->name,
                    'email' => $primaryContact->email,
                    'phone' => $primaryContact->phone,
                ]] : [],
            ],
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

    /**
     * Login as partner client - same response as LoginTabloCodeAction::loginAsPartnerClient()
     */
    private function loginAsPartnerClient(int $clientId)
    {
        $client = PartnerClient::findOrFail($clientId);
        $client->recordLogin();

        $plainTextToken = Str::random(64);
        $hashedToken = hash('sha256', $plainTextToken);

        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => PartnerClient::class,
            'tokenable_id' => $client->id,
            'name' => 'dev-client-token',
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
            ->map(fn ($album) => $album->toClientArray());

        return response()->json([
            'user' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'type' => 'partner-client',
                'isRegistered' => (bool) $client->is_registered,
            ],
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'canRegister' => $client->hasAlbumWithRegistrationAllowed(),
            ],
            'albums' => $albums,
            'token' => $plainTextToken,
            'tokenType' => 'client',
            'loginType' => 'client',
        ]);
    }

    /**
     * Find entity by user type and identifier
     */
    private function findEntity(string $userType, int $identifier): mixed
    {
        return match ($userType) {
            'partner', 'marketer', 'designer', 'printer', 'assistant' => User::find($identifier),
            'tablo-guest' => TabloGuestSession::find($identifier),
            'partner-client' => PartnerClient::find($identifier),
        };
    }
}

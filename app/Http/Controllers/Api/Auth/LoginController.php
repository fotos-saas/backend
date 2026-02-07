<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Concerns\ResolvesPartner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginCodeRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Models\LoginAudit;
use App\Models\TabloGuestSession;
use App\Models\TabloProject;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\AuthenticationService;
use App\Services\MagicLinkService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Login Controller
 *
 * Handles standard login methods:
 * - Email/password login
 * - WorkSession code login
 * - Magic link login
 *
 * For Tablo-specific logins (access code, share token, preview), see TabloLoginController
 */
class LoginController extends Controller
{
    use ResolvesPartner;

    public function __construct(
        private AuthenticationService $authService
    ) {}

    /**
     * Login with email and password
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            if ($user) {
                $this->authService->incrementFailedAttempts($credentials['email']);
            }

            $this->authService->logLoginAttempt(
                email: $credentials['email'],
                method: LoginAudit::METHOD_PASSWORD,
                success: false,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                user: $user,
                failureReason: LoginAudit::FAILURE_INVALID_CREDENTIALS
            );

            return response()->json([
                'message' => 'Hibás email vagy jelszó',
            ], 401);
        }

        if ($this->authService->isEmailVerificationRequired() && ! $user->hasVerifiedEmail()) {
            $this->authService->logLoginAttempt(
                email: $credentials['email'],
                method: LoginAudit::METHOD_PASSWORD,
                success: false,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                user: $user,
                failureReason: LoginAudit::FAILURE_EMAIL_NOT_VERIFIED
            );

            return response()->json([
                'message' => 'Az email címed még nincs megerősítve. Kérjük, erősítsd meg az email címedet a bejelentkezéshez.',
                'email_not_verified' => true,
            ], 403);
        }

        $this->authService->clearFailedAttempts($credentials['email']);
        $this->authService->recordSuccessfulLogin($user, $ipAddress);

        $token = $this->authService->createTokenWithMetadata(
            user: $user,
            name: 'auth-token',
            loginMethod: LoginAudit::METHOD_PASSWORD,
            ipAddress: $ipAddress,
            deviceName: $this->parseDeviceName($userAgent)
        );

        $this->authService->logLoginAttempt(
            email: $credentials['email'],
            method: LoginAudit::METHOD_PASSWORD,
            success: true,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            user: $user
        );

        $roles = $user->getRoleNames()->toArray();
        $partnerRoles = ['partner', 'designer', 'marketer', 'printer', 'assistant'];
        $hasPartnerRole = ! empty(array_intersect($roles, $partnerRoles));

        $partner = $hasPartnerRole ? $this->resolvePartner($user->id) : null;

        // Árva partner user: van partner role, de nincs Partner rekord → 401
        if ($hasPartnerRole && ! $partner) {
            return response()->json([
                'message' => 'Érvénytelen fiók. Kérjük, lépj kapcsolatba az adminisztrátorral.',
            ], 401);
        }

        $response = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'type' => $user->isMarketer() ? 'marketer' : 'registered',
                'roles' => $roles,
                'passwordSet' => (bool) $user->password_set,
                'has_partner' => (bool) $partner,
                'partner_id' => $partner?->id,
            ],
            'token' => $token,
        ];

        // Handle tablo-guest login (user with internal tablo email)
        $response = $this->handleTabloGuestLogin($user, $response);

        return response()->json($response);
    }

    /**
     * Login with 6-digit access code (WorkSession based)
     */
    public function loginCode(LoginCodeRequest $request)
    {
        $code = $request->input('code');
        $workSession = WorkSession::where('digit_code', $code)->first();

        if (! $workSession) {
            return response()->json([
                'message' => 'Ez a munkamenet már megszűnt vagy lejárt',
            ], 401);
        }

        if (! $workSession->digit_code_enabled) {
            return response()->json([
                'message' => 'A belépési kód le van tiltva',
            ], 401);
        }

        if ($workSession->digit_code_expires_at && $workSession->digit_code_expires_at->isPast()) {
            return response()->json([
                'message' => 'A belépési kód lejárt',
            ], 401);
        }

        if ($workSession->status !== 'active') {
            return response()->json([
                'message' => 'Ez a munkamenet már nem elérhető',
            ], 401);
        }

        $guestUser = User::create([
            'name' => 'Guest-'.$workSession->id.'-'.Str::random(6),
            'email' => null,
            'password' => null,
        ]);

        $guestUser->assignRole(User::ROLE_GUEST);
        $guestUser->refresh();

        $workSession->users()->attach($guestUser->id);

        $tokenResult = $guestUser->createToken('auth-token');
        $tokenResult->accessToken->work_session_id = $workSession->id;
        $tokenResult->accessToken->save();
        $token = $tokenResult->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $guestUser->id,
                'name' => $guestUser->name,
                'email' => $guestUser->email,
                'phone' => $guestUser->phone,
                'address' => $guestUser->address,
                'type' => 'guest',
                'workSessionId' => $workSession->id,
                'workSessionName' => $workSession->name,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Login with magic link token
     */
    public function loginMagic(string $token, MagicLinkService $magicLinkService)
    {
        $magicToken = $magicLinkService->validateToken($token);

        if (! $magicToken) {
            return response()->json([
                'message' => 'Érvénytelen vagy lejárt magic link',
            ], 401);
        }

        $user = $magicLinkService->consume($token);
        $workSession = $magicToken->workSession;

        $isFirstLogin = $user->isFirstLogin();
        if ($isFirstLogin) {
            $user->markFirstLogin();
        }

        $authToken = $user->createToken('magic-link-login')->plainTextToken;

        $response = [
            'message' => 'Sikeres bejelentkezés',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'type' => 'registered',
                'first_login' => $isFirstLogin,
                'password_set' => $user->hasSetPassword(),
            ],
            'token' => $authToken,
        ];

        if ($workSession) {
            $response['work_session'] = [
                'id' => $workSession->id,
                'name' => $workSession->name,
            ];
        }

        return response()->json($response);
    }

    /**
     * Handle tablo-guest user login (user with internal tablo email)
     */
    private function handleTabloGuestLogin(User $user, array $response): array
    {
        if (!preg_match('/^tablo-guest-(\d+)-[a-zA-Z0-9]+@internal\.local$/', $user->email ?? '', $matches)) {
            return $response;
        }

        $projectId = (int) $matches[1];
        $project = TabloProject::find($projectId);

        if (!$project) {
            return $response;
        }

        $guestSession = TabloGuestSession::where('guest_name', $user->name)
            ->where('tablo_project_id', $projectId)
            ->latest('last_activity_at')
            ->first();

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

        if ($project->partner) {
            $branding = $project->partner->getActiveBranding();
            if ($branding) {
                $projectData['branding'] = $branding;
            }
        }

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

    /**
     * Parse device name from user agent
     */
    private function parseDeviceName(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        $browser = 'Unknown';
        $os = 'Unknown';

        if (str_contains($userAgent, 'Chrome')) {
            $browser = 'Chrome';
        } elseif (str_contains($userAgent, 'Firefox')) {
            $browser = 'Firefox';
        } elseif (str_contains($userAgent, 'Safari')) {
            $browser = 'Safari';
        } elseif (str_contains($userAgent, 'Edge')) {
            $browser = 'Edge';
        }

        if (str_contains($userAgent, 'Windows')) {
            $os = 'Windows';
        } elseif (str_contains($userAgent, 'Mac')) {
            $os = 'Mac';
        } elseif (str_contains($userAgent, 'Linux')) {
            $os = 'Linux';
        } elseif (str_contains($userAgent, 'Android')) {
            $os = 'Android';
        } elseif (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) {
            $os = 'iOS';
        }

        return "{$browser} on {$os}";
    }
}

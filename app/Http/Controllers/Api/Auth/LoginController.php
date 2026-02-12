<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\HandleTabloGuestLoginAction;
use App\Actions\Auth\LoginWithCodeAction;
use App\Constants\TokenNames;
use App\Events\LoginFailed;
use App\Events\UserLoggedIn;
use App\Http\Controllers\Api\Concerns\ResolvesPartner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginCodeRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Models\LoginAudit;
use App\Models\User;
use App\Services\AuthenticationService;
use App\Services\MagicLinkService;
use Illuminate\Support\Facades\Hash;

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

            LoginFailed::dispatch($credentials['email'], 'password', $ipAddress, 'invalid_credentials', $userAgent);

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

            LoginFailed::dispatch($credentials['email'], 'password', $ipAddress, 'email_not_verified', $userAgent);

            return response()->json([
                'message' => 'Az email címed még nincs megerősítve. Kérjük, erősítsd meg az email címedet a bejelentkezéshez.',
                'email_not_verified' => true,
            ], 403);
        }

        $this->authService->clearFailedAttempts($credentials['email']);
        $this->authService->recordSuccessfulLogin($user, $ipAddress);

        $token = $this->authService->createTokenWithMetadata(
            user: $user,
            name: TokenNames::AUTH,
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

        UserLoggedIn::dispatch($user, 'password', $ipAddress, $userAgent);

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
        $response = app(HandleTabloGuestLoginAction::class)->execute($user, $response);

        return response()->json($response);
    }

    /**
     * Login with 6-digit access code (WorkSession based)
     */
    public function loginCode(LoginCodeRequest $request, LoginWithCodeAction $action)
    {
        $result = $action->execute($request->input('code'));

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json($result['data']);
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

        $authToken = $user->createToken(TokenNames::MAGIC_LINK)->plainTextToken;

        UserLoggedIn::dispatch($user, 'magic_link', $request->ip(), $request->userAgent());

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

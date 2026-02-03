<?php

namespace App\Http\Controllers\Api;

use App\Enums\TabloProjectStatus;
use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\LoginCodeRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\LoginTabloCodeRequest;
use App\Http\Requests\Api\QrRegistrationRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Models\EmailEvent;
use App\Models\LoginAudit;
use App\Models\Setting;
use App\Models\TabloGuestSession;
use App\Models\PartnerClient;
use App\Models\TabloProject;
use App\Models\TabloProjectAccessLog;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\AuthenticationService;
use App\Services\EmailService;
use App\Services\EmailVariableService;
use App\Services\MagicLinkService;
use App\Services\QrRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        private AuthenticationService $authService,
        private QrRegistrationService $qrService
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
            // Log failed attempt and increment counter
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

        // Check if email verification is required
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

        // Clear failed attempts and record successful login
        $this->authService->clearFailedAttempts($credentials['email']);
        $this->authService->recordSuccessfulLogin($user, $ipAddress);

        // Create Sanctum token with metadata
        $token = $this->authService->createTokenWithMetadata(
            user: $user,
            name: 'auth-token',
            loginMethod: LoginAudit::METHOD_PASSWORD,
            ipAddress: $ipAddress,
            deviceName: $this->parseDeviceName($userAgent)
        );

        // Log successful login
        $this->authService->logLoginAttempt(
            email: $credentials['email'],
            method: LoginAudit::METHOD_PASSWORD,
            success: true,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            user: $user
        );

        $response = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'type' => $user->isMarketer() ? 'marketer' : 'registered',
                'roles' => $user->getRoleNames()->toArray(),
                'passwordSet' => (bool) $user->password_set,
            ],
            'token' => $token,
        ];

        // Ha tablo-guest felhasználó (internal email), keressük meg a hozzá tartozó projektet
        // Email formátum: tablo-guest-{projectId}-xxx@internal.local
        \Log::info('[Auth] Checking for tablo-guest login', ['user_id' => $user->id]);

        if (preg_match('/^tablo-guest-(\d+)-[a-zA-Z0-9]+@internal\.local$/', $user->email ?? '', $matches)) {
            $projectId = (int) $matches[1];
            \Log::info('[Auth] Tablo-guest pattern matched', ['projectId' => $projectId]);
            $project = TabloProject::find($projectId);

            if ($project) {
                \Log::info('[Auth] Project found', ['projectName' => $project->display_name]);

                // Keressük meg a guest session-t a user neve alapján (mert az email a regisztráció során megadott email)
                $guestSession = TabloGuestSession::where('guest_name', $user->name)
                    ->where('tablo_project_id', $projectId)
                    ->latest('last_activity_at')
                    ->first();

                $response['user']['type'] = 'tablo-guest';
                $response['project'] = [
                    'id' => $project->id,
                    'name' => $project->display_name,
                    'schoolName' => $project->school?->name,
                    'className' => $project->class_name,
                    'classYear' => $project->class_year,
                    'samplesCount' => $project->getMedia('samples')->count(),
                    'activePollsCount' => $project->polls()->active()->count(),
                    'contacts' => [],
                ];
                $response['tokenType'] = 'code';
                $response['canFinalize'] = true;

                if ($guestSession) {
                    $response['guestSession'] = [
                        'sessionToken' => $guestSession->session_token,
                        'guestName' => $guestSession->guest_name,
                    ];
                }
            }
        }

        return response()->json($response);
    }

    /**
     * Login with 6-digit access code (WorkSession based - creates guest user)
     */
    public function loginCode(LoginCodeRequest $request)
    {
        $code = $request->input('code');

        // Find work session by code (without status filter for detailed error messages)
        $workSession = WorkSession::where('digit_code', $code)->first();

        if (! $workSession) {
            return response()->json([
                'message' => 'Ez a munkamenet már megszűnt vagy lejárt',
            ], 401);
        }

        // Check if digit code is enabled
        if (! $workSession->digit_code_enabled) {
            return response()->json([
                'message' => 'A belépési kód le van tiltva',
            ], 401);
        }

        // Check if not expired
        if ($workSession->digit_code_expires_at && $workSession->digit_code_expires_at->isPast()) {
            return response()->json([
                'message' => 'A belépési kód lejárt',
            ], 401);
        }

        // Check if work session is active
        if ($workSession->status !== 'active') {
            return response()->json([
                'message' => 'Ez a munkamenet már nem elérhető',
            ], 401);
        }

        // Create guest user for this work session
        // Each login creates a new guest user (anonymous access)
        // SECURITY: Create user without 'role' (mass assignment protected)
        $guestUser = User::create([
            'name' => 'Guest-'.$workSession->id.'-'.Str::random(6),
            'email' => null,
            'password' => null,
        ]);

        // Assign guest role explicitly (not mass assignable for security)
        $guestUser->assignRole(User::ROLE_GUEST);

        // Refresh to ensure user is persisted
        $guestUser->refresh();

        // Attach user to work session
        $workSession->users()->attach($guestUser->id);

        // Generate Sanctum token with work_session_id
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
     * Login with 6-digit access code
     * Supports both:
     * - TabloProject codes (tablo diák workflow)
     * - PartnerClient codes (partner ügyfél album kezelés)
     */
    public function loginTabloCode(LoginTabloCodeRequest $request)
    {
        $code = $request->input('code');

        // 1. Először próbáljuk TabloProject-ként (tablo diák kód)
        $tabloProject = TabloProject::where('access_code', $code)->first();

        // 2. Ha nincs TabloProject, próbáljuk PartnerClient-ként (partner ügyfél kód)
        if (!$tabloProject) {
            return $this->loginAsPartnerClient($code, $request);
        }

        // Check if access code is enabled
        if (! $tabloProject->access_code_enabled) {
            return response()->json([
                'message' => 'A belépési kód le van tiltva',
            ], 401);
        }

        // Check if not expired
        if ($tabloProject->access_code_expires_at && $tabloProject->access_code_expires_at->isPast()) {
            return response()->json([
                'message' => 'A belépési kód lejárt',
            ], 401);
        }

        // Check if project status is not "done" or "in_print"
        if (in_array($tabloProject->status, [TabloProjectStatus::Done, TabloProjectStatus::InPrint])) {
            return response()->json([
                'message' => 'Ez a projekt már lezárult',
            ], 401);
        }

        // Get primary contact for this project (if exists)
        // A 'code' belépéssel a felhasználó kapcsolattartóként működik
        $primaryContact = $tabloProject->contacts()->where('is_primary', true)->first()
            ?? $tabloProject->contacts()->first();

        // Create project-specific guest user (prevents session mixing between different projects)
        $guestEmail = 'tablo-guest-' . $tabloProject->id . '-' . Str::random(8) . '@internal.local';

        // SECURITY: Create user without 'role' (mass assignment protected)
        // Ha van kapcsolattartó, annak nevét használjuk
        $tabloGuestUser = User::create([
            'email' => $guestEmail,
            'name' => $primaryContact?->name ?? ('Kapcsolattartó - ' . $tabloProject->display_name),
            'password' => null,
        ]);

        // Assign guest role explicitly (not mass assignable for security)
        $tabloGuestUser->assignRole(User::ROLE_GUEST);

        // Generate Sanctum token with tablo_project_id and contact_id
        // A contact_id beállítása lehetővé teszi a newsfeed/forum használatát guest session nélkül
        $tokenResult = $tabloGuestUser->createToken('tablo-auth-token');
        $tokenResult->accessToken->tablo_project_id = $tabloProject->id;
        $tokenResult->accessToken->contact_id = $primaryContact?->id;
        $tokenResult->accessToken->save();
        $token = $tokenResult->plainTextToken;

        // Automatikusan létrehozunk/újrahasználunk egy guest session-t a kapcsolattartó számára
        // Ez lehetővé teszi a poke rendszer használatát kódos belépésnél is
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

        // Ha már létezett, frissítsük az aktivitást
        if (!$guestSession->wasRecentlyCreated) {
            $guestSession->update([
                'last_activity_at' => now(),
                'is_coordinator' => true,
            ]);
        }

        return response()->json([
            'user' => [
                'id' => $tabloGuestUser->id,
                'name' => $tabloGuestUser->name,
                'email' => $tabloGuestUser->email,
                'type' => 'tablo-guest',
                'passwordSet' => (bool) $tabloGuestUser->password_set,
            ],
            'project' => [
                'id' => $tabloProject->id,
                'name' => $tabloProject->display_name,
                'schoolName' => $tabloProject->school?->name,
                'className' => $tabloProject->class_name,
                'classYear' => $tabloProject->class_year,
                'samplesCount' => $tabloProject->getMedia('samples')->count(),
                'activePollsCount' => $tabloProject->polls()->active()->count(),
                // Contacts array az értesítési rendszerhez (contact ID szükséges)
                'contacts' => $primaryContact ? [[
                    'id' => $primaryContact->id,
                    'name' => $primaryContact->name,
                    'email' => $primaryContact->email,
                    'phone' => $primaryContact->phone,
                ]] : [],
            ],
            'token' => $token,
            // Token típus - route guard-hoz és conditional menu-hoz
            'tokenType' => 'code',
            'canFinalize' => true,
            // Guest session token a poke rendszerhez
            'guestSession' => [
                'sessionToken' => $guestSession->session_token,
                'guestName' => $guestSession->guest_name,
            ],
            // Login típus - frontend tudja melyik oldalra irányítson
            'loginType' => 'tablo',
        ]);
    }

    /**
     * Login as PartnerClient (partner ügyfél kóddal)
     * Ez a metódus akkor hívódik, ha a kód nem TabloProject kód
     *
     * FONTOS: Ha a kliens regisztrált (is_registered = true),
     * a kód alapú belépés NEM működik - email/jelszóval kell belépnie!
     */
    private function loginAsPartnerClient(string $code, Request $request)
    {
        // Keressük a PartnerClient-et access_code alapján
        $client = PartnerClient::byAccessCode($code)->first();

        if (!$client) {
            return response()->json([
                'message' => 'Érvénytelen belépési kód',
            ], 401);
        }

        // Ha a kliens regisztrált, NEM léphet be kóddal!
        if ($client->is_registered) {
            return response()->json([
                'message' => 'Ez a fiók már regisztrálva van. Kérlek használd az email/jelszó bejelentkezést!',
                'requiresPasswordLogin' => true,
                'email' => $client->email,
            ], 401);
        }

        // Ellenőrizzük, hogy a kód érvényes és engedélyezett-e
        if (!$client->canLoginWithCode()) {
            return response()->json([
                'message' => 'A belépési kód le van tiltva vagy lejárt.',
            ], 401);
        }

        // Ellenőrizzük, hogy a partner rendelkezik-e a client_orders funkcióval
        if (!$client->partner || !$client->partner->hasFeature('client_orders')) {
            return response()->json([
                'message' => 'A funkció nem elérhető',
            ], 403);
        }

        // Rögzítsük a belépést
        $client->recordLogin();

        // Hozzunk létre tokent a kliensnek
        $plainTextToken = Str::random(64);
        $hashedToken = hash('sha256', $plainTextToken);

        \Illuminate\Support\Facades\DB::table('personal_access_tokens')->insert([
            'tokenable_type' => PartnerClient::class,
            'tokenable_id' => $client->id,
            'name' => 'client-auth-token',
            'token' => $hashedToken,
            'abilities' => json_encode(['client']),
            'partner_client_id' => $client->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Albumok lekérése
        $albums = $client->albums()
            ->where('status', '!=', 'draft') // Csak aktív albumok
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

        // Van-e olyan album ami engedélyezi a regisztrációt?
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
            // Login típus - frontend tudja melyik oldalra irányítson
            'loginType' => 'client',
        ]);
    }

    /**
     * Login with share token (TabloProject based - no code required)
     * Uses a shared tablo-guest user like loginTabloCode
     *
     * Supports restore parameter for session restoration via magic link.
     */
    public function loginTabloShare(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'restore' => ['nullable', 'string', 'size:64'],
        ]);

        $token = $request->input('token');
        $restoreToken = $request->input('restore');

        // Find tablo project by share token
        $tabloProject = TabloProject::findByShareToken($token);

        if (! $tabloProject) {
            return response()->json([
                'message' => 'Érvénytelen vagy lejárt megosztási link',
            ], 401);
        }

        // Check if project status is not "done" or "in_print"
        if (in_array($tabloProject->status, [TabloProjectStatus::Done, TabloProjectStatus::InPrint])) {
            return response()->json([
                'message' => 'Ez a projekt már lezárult',
            ], 401);
        }

        // Restore token kezelés
        $restoredSession = null;
        if ($restoreToken) {
            $restoredSession = TabloGuestSession::where('restore_token', $restoreToken)
                ->where('tablo_project_id', $tabloProject->id)
                ->where('restore_token_expires_at', '>', now())
                ->verified()
                ->active()
                ->first();

            if ($restoredSession) {
                // Token invalidálás (egyszer használatos)
                $restoredSession->update([
                    'restore_token' => null,
                    'restore_token_expires_at' => null,
                    'last_activity_at' => now(),
                ]);

                \Log::info('[Auth] Guest session restored via magic link', [
                    'project_id' => $tabloProject->id,
                    'session_id' => $restoredSession->id,
                ]);
            }
        }

        // Log access
        TabloProjectAccessLog::logAccess(
            $tabloProject->id,
            $restoredSession ? 'restore_link' : 'share_token',
            $request->ip(),
            $request->userAgent(),
            $restoredSession ? ['restored_session_id' => $restoredSession->id] : null
        );

        // Create project-specific guest user (prevents session mixing between different projects)
        $guestEmail = 'tablo-guest-' . $tabloProject->id . '-' . Str::random(8) . '@internal.local';

        // SECURITY: Create user without 'role' (mass assignment protected)
        $tabloGuestUser = User::create([
            'email' => $guestEmail,
            'name' => $restoredSession ? $restoredSession->guest_name : ('Tablo Guest - ' . $tabloProject->display_name),
            'password' => null,
        ]);

        // Assign guest role explicitly (not mass assignable for security)
        $tabloGuestUser->assignRole(User::ROLE_GUEST);

        // Generate Sanctum token with tablo_project_id
        $tokenResult = $tabloGuestUser->createToken('tablo-share-token');
        $tokenResult->accessToken->tablo_project_id = $tabloProject->id;
        $tokenResult->accessToken->save();
        $authToken = $tokenResult->plainTextToken;

        $response = [
            'user' => [
                'id' => $tabloGuestUser->id,
                'name' => $tabloGuestUser->name,
                'email' => $tabloGuestUser->email,
                'type' => 'tablo-guest',
                'passwordSet' => true, // Share link-es belépésnél nem kérünk jelszót
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
            // Token típus - route guard-hoz és conditional menu-hoz
            'tokenType' => 'share',
            'canFinalize' => false, // Share token nem véglegesíthet
        ];

        // Ha van restored session, adjuk vissza a guest session adatokat
        if ($restoredSession) {
            $response['restoredSession'] = [
                'sessionToken' => $restoredSession->session_token,
                'guestName' => $restoredSession->guest_name,
                'guestEmail' => $restoredSession->guest_email,
            ];
        }

        return response()->json($response);
    }

    /**
     * Login with admin preview token (one-time use, consumed on access)
     * Token is invalidated after successful login
     */
    public function loginTabloPreview(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string', 'size:64'],
        ]);

        $token = $request->input('token');

        // Find tablo project by admin preview token
        $tabloProject = TabloProject::findByAdminPreviewToken($token);

        if (! $tabloProject) {
            return response()->json([
                'message' => 'Érvénytelen vagy lejárt előnézeti link',
            ], 401);
        }

        // Consume token (invalidate after use)
        $tabloProject->consumeAdminPreviewToken();

        // Log access
        TabloProjectAccessLog::logAccess(
            $tabloProject->id,
            'admin_preview',
            $request->ip(),
            $request->userAgent(),
            ['one_time' => true]
        );

        // Create project-specific guest user (prevents session mixing between different projects)
        $guestEmail = 'tablo-guest-' . $tabloProject->id . '-' . Str::random(8) . '@internal.local';

        // SECURITY: Create user without 'role' (mass assignment protected)
        $tabloGuestUser = User::create([
            'email' => $guestEmail,
            'name' => 'Tablo Guest - ' . $tabloProject->display_name,
            'password' => null,
        ]);

        // Assign guest role explicitly (not mass assignable for security)
        $tabloGuestUser->assignRole(User::ROLE_GUEST);

        // Generate Sanctum token with tablo_project_id and preview flag
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
                'passwordSet' => true, // Preview belépésnél nem kérünk jelszót
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
            // Token típus - route guard-hoz és conditional menu-hoz
            'tokenType' => 'preview',
            'canFinalize' => false, // Preview token nem véglegesíthet
        ]);
    }

    /**
     * Register new user
     */
    public function register(RegisterRequest $request)
    {
        // SECURITY: Create user without 'role' (mass assignment protected)
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'phone' => $request->input('phone'),
        ]);

        // Assign customer role explicitly (not mass assignable for security)
        $user->assignRole(User::ROLE_CUSTOMER);

        // Trigger UserRegistered event for welcome email
        event(new UserRegistered($user));

        return response()->json([
            'message' => 'Sikeres regisztráció! Most már bejelentkezhetsz.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    /**
     * Forgot password (send reset email)
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $request->input('email');
        
        // Check if user exists
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            // Return success even if user doesn't exist (security)
            return response()->json([
                'message' => 'Ha az email cím létezik, küldtünk egy jelszó-visszaállítási linket.',
            ]);
        }

        // Generate reset token
        $token = Str::random(64);
        
        // Store token in database
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'email' => $email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Send email with reset link
        try {
            Mail::send('emails.password-reset', [
                'token' => $token,
                'email' => $email,
                'user' => $user,
            ], function ($message) use ($email, $user) {
                $message->to($email, $user->name)
                        ->subject('Jelszó visszaállítás - Photo Stack');
            });

            return response()->json([
                'message' => 'Ha az email cím létezik, küldtünk egy jelszó-visszaállítási linket.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Password reset email failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Ha az email cím létezik, küldtünk egy jelszó-visszaállítási linket.',
            ]);
        }
    }

    /**
     * Request magic link via email
     */
    public function requestMagicLink(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'work_session_code' => ['nullable', 'string', 'size:6'],
            'include_code' => ['nullable', 'boolean'],
        ]);

        $email = $request->input('email');
        $workSessionCode = $request->input('work_session_code');
        $includeCode = $request->input('include_code', false);

        // Check if user exists
        $user = User::where('email', $email)->first();

        if (!$user) {
            // Return success even if user doesn't exist (security best practice)
            return response()->json([
                'message' => 'Ha az email cím létezik, küldtünk egy belépési linket.',
                'success' => true,
            ]);
        }

        // Find work session if code provided
        $workSession = null;
        if ($workSessionCode) {
            $workSession = WorkSession::byDigitCode($workSessionCode)->first();

            if (!$workSession || !$workSession->isDigitCodeValid()) {
                return response()->json([
                    'message' => 'Érvénytelen munkamenet kód.',
                    'success' => false,
                ], 422);
            }
        }

        try {
            // Generate magic link token
            $magicLinkService = app(MagicLinkService::class);

            if ($workSession) {
                $token = $magicLinkService->generateForWorkSession($user->id, $workSession->id);
            } else {
                $token = $magicLinkService->generate($user->id);
            }

            // Prepare auth data for email variables
            $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
            $authData = [
                'magic_link' => $frontendUrl . '/auth/magic/' . $token,
                'include_code' => $includeCode && $workSession && $workSession->digit_code,
                'digit_code' => $workSession?->digit_code ?? '',
            ];

            // Send email using EmailService
            $emailService = app(EmailService::class);
            $emailVariableService = app(EmailVariableService::class);

            // Find the magic login email event
            $emailEvent = EmailEvent::where('key', 'user_magic_login')
                ->where('is_active', true)
                ->first();

            if (!$emailEvent || !$emailEvent->emailTemplate) {
                \Log::error('Magic login email event or template not found');
                return response()->json([
                    'message' => 'Email sablon nincs konfigurálva.',
                    'success' => false,
                ], 500);
            }

            // Resolve all variables
            $variables = $emailVariableService->resolveVariables(
                user: $user,
                album: $workSession?->album,
                workSession: $workSession,
                authData: $authData
            );

            // Send the email
            $emailService->send(
                emailEvent: $emailEvent,
                recipientEmail: $user->email,
                recipientName: $user->name,
                variables: $variables
            );

            return response()->json([
                'message' => 'A belépési linket elküldtük az email címedre.',
                'success' => true,
            ]);

        } catch (\Exception $e) {
            \Log::error('Magic link request failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Hiba történt az email küldése során.',
                'success' => false,
            ], 500);
        }
    }

    /**
     * Refresh token (return current user)
     */
    public function refresh(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $response = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'type' => $user->role === User::ROLE_GUEST ? 'guest' : 'registered',
                'accessCode' => $user->access_code,
            ],
        ];

        // Only return workSessionId for guest users (they have exactly 1 work session)
        if ($user->role === User::ROLE_GUEST) {
            $workSession = $user->workSessions()->first();
            if ($workSession) {
                $response['user']['workSessionId'] = $workSession->id;
                $response['user']['workSessionName'] = $workSession->name;
            }
        }

        return response()->json($response);
    }

    /**
     * Logout (revoke token and clear user cache)
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        $userId = $user?->id;

        // Clear all album photos cache for this user
        if ($userId) {
            try {
                // Pattern: album_photos:*:{user_id}:*
                $pattern = config('database.redis.options.prefix') . 'album_photos:*:' . $userId . ':*';
                $keys = Redis::connection()->keys($pattern);

                if (!empty($keys)) {
                    // Remove prefix from keys before deleting
                    $prefix = config('database.redis.options.prefix');
                    $keysWithoutPrefix = array_map(function($key) use ($prefix) {
                        return str_replace($prefix, '', $key);
                    }, $keys);

                    Cache::deleteMultiple($keysWithoutPrefix);

                    \Log::info('[Auth] User cache cleared on logout', [
                        'user_id' => $userId,
                        'keys_deleted' => count($keys),
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('[Auth] Failed to clear cache on logout', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Revoke Sanctum token
        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sikeresen kijelentkeztél',
        ]);
    }

    /**
     * Validate magic link token (without consuming it)
     */
    public function validateMagicToken(string $token, MagicLinkService $magicLinkService)
    {
        $magicToken = $magicLinkService->validateToken($token);

        if (! $magicToken) {
            return response()->json([
                'message' => 'Érvénytelen vagy lejárt magic link',
                'valid' => false,
            ], 401);
        }

        $user = $magicToken->user;
        $workSession = $magicToken->workSession;

        return response()->json([
            'valid' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'first_login' => $user->isFirstLogin(),
                'password_set' => $user->hasSetPassword(),
            ],
            'work_session' => $workSession ? [
                'id' => $workSession->id,
                'name' => $workSession->name,
            ] : null,
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

        // Mark first login
        $isFirstLogin = $user->isFirstLogin();
        if ($isFirstLogin) {
            $user->markFirstLogin();
        }

        // Generate Sanctum token for API authentication
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

        // Add work session info if token is tied to a work session
        if ($workSession) {
            $response['work_session'] = [
                'id' => $workSession->id,
                'name' => $workSession->name,
            ];
        }

        return response()->json($response);
    }

    /**
     * Set password (for first-time login or password change)
     */
    public function setPassword(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Update password
        $user->password = Hash::make($validated['password']);
        $user->password_set = true;
        $user->save();

        // Send password changed confirmation email
        $emailEvent = EmailEvent::where('event_type', 'password_changed')
            ->where('is_active', true)
            ->first();

        if ($emailEvent && $emailEvent->emailTemplate) {
            $emailService = app(EmailService::class);
            $variableService = app(EmailVariableService::class);

            $variables = $variableService->resolveVariables(user: $user);

            $emailService->sendFromTemplate(
                template: $emailEvent->emailTemplate,
                recipientEmail: $user->email,
                variables: $variables,
                recipientUser: $user,
                eventType: 'password_changed'
            );
        }

        return response()->json([
            'message' => 'Jelszó sikeresen beállítva',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'password_set' => true,
            ],
        ]);
    }

    /**
     * Send bulk work session invites to multiple email addresses
     * Uses the authenticated user's latest active work session
     */
    public function bulkWorkSessionInvite(Request $request)
    {
        $validated = $request->validate([
            'emails' => ['required', 'array', 'max:30'],
            'emails.*' => ['required', 'email'],
        ]);

        $emails = $validated['emails'];
        $sent = [];
        $failed = [];

        // Get authenticated user's latest active work session
        $workSession = $request->user()->workSessions()
            ->where('work_sessions.status', 'active')
            ->latest('work_sessions.created_at')
            ->first();

        if (!$workSession) {
            return response()->json([
                'message' => 'Nincs aktív munkameneted. Először hozz létre egy munkamenetet!',
                'sent' => [],
                'failed' => $emails,
                'total' => count($emails),
            ], 400);
        }

        $emailService = app(EmailService::class);
        $emailVariableService = app(EmailVariableService::class);

        // Find work session invite email event
        $emailEvent = EmailEvent::where('event_type', 'work_session_invite')
            ->where('is_active', true)
            ->first();

        if (!$emailEvent || !$emailEvent->emailTemplate) {
            return response()->json([
                'message' => 'Email sablon nincs konfigurálva.',
                'sent' => [],
                'failed' => $emails,
                'total' => count($emails),
            ], 500);
        }

        $delay = 0; // Initial delay (seconds)

        foreach ($emails as $email) {
            // Find or create user by email
            $user = User::where('email', $email)
                ->whereNotNull('email')
                ->first();

            // If user doesn't exist, create a new one automatically
            if (!$user) {
                try {
                    // SECURITY: Create user without 'role' (mass assignment protected)
                    $user = User::create([
                        'email' => $email,
                        'name' => explode('@', $email)[0], // Use email prefix as name
                        'password' => null, // No password set yet
                        'password_set' => false,
                    ]);

                    // Assign role explicitly (not mass assignable for security)
                    $user->assignRole('user');
                } catch (\Exception $e) {
                    logger()->error('Failed to create user for work session invite', [
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                    $failed[] = $email;
                    continue;
                }
            }

            try {
                // Resolve email variables (NO magic link generation!)
                $variables = $emailVariableService->resolveVariables(
                    user: $user,
                    album: $workSession->album,
                    workSession: $workSession,
                    authData: [
                        'digit_code' => $workSession->digit_code,
                        'work_session_name' => $workSession->album->title ?? 'Munkamenet',
                    ]
                );

                // Dispatch job with 30-second delay between each email
                \App\Jobs\SendMagicLinkEmailJob::dispatch(
                    $user,
                    $emailEvent->emailTemplate,
                    $variables,
                    'work_session_invite'
                )->delay(now()->addSeconds($delay));

                $sent[] = $email;
                $delay += 30; // Increase delay by 30 seconds for next email
            } catch (\Exception $e) {
                logger()->error('Failed to dispatch work session invite job', [
                    'email' => $email,
                    'work_session_id' => $workSession->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $failed[] = $email;
            }
        }

        return response()->json([
            'message' => 'Meghívók elküldve',
            'sent' => $sent,
            'failed' => $failed,
            'total' => count($emails),
            'work_session' => [
                'id' => $workSession->id,
                'name' => $workSession->album->title ?? 'Munkamenet',
                'digit_code' => $workSession->digit_code,
            ],
        ]);
    }

    /**
     * Validate current work session status (for digit code guest users)
     * This endpoint triggers the CheckWorkSessionStatus middleware
     */
    public function validateSession(Request $request)
    {
        // If we reached here, the middleware validated the session successfully
        return response()->json([
            'valid' => true,
            'message' => 'Munkamenet érvényes',
        ]);
    }

    // ==========================================
    // PASSWORD RESET
    // ==========================================

    /**
     * Reset password with token
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        $email = $request->input('email');
        $token = $request->input('token');
        $password = $request->input('password');

        // Find password reset record
        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (! $record || ! Hash::check($token, $record->token)) {
            return response()->json([
                'message' => 'Érvénytelen vagy lejárt visszaállítási link.',
            ], 400);
        }

        // Check if token is not expired (1 hour)
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return response()->json([
                'message' => 'A visszaállítási link lejárt. Kérj új linket.',
            ], 400);
        }

        // Find and update user
        $user = User::where('email', $email)->first();

        if (! $user) {
            return response()->json([
                'message' => 'Felhasználó nem található.',
            ], 404);
        }

        // Update password
        $user->password = Hash::make($password);
        $user->password_set = true;
        $user->save();

        // Delete password reset token
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Clear any lockout
        $this->authService->clearFailedAttempts($email);

        \Log::info('[Auth] Password reset successful', ['user_id' => $user->id]);

        return response()->json([
            'message' => 'A jelszavad sikeresen megváltozott. Most már bejelentkezhetsz.',
        ]);
    }

    /**
     * Change password (authenticated user)
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();
        $password = $request->input('password');

        // Update password
        $user->password = Hash::make($password);
        $user->password_set = true;
        $user->save();

        \Log::info('[Auth] Password changed', ['user_id' => $user->id]);

        return response()->json([
            'message' => 'A jelszavad sikeresen megváltozott.',
        ]);
    }

    // ==========================================
    // EMAIL VERIFICATION
    // ==========================================

    /**
     * Verify email with signed URL
     */
    public function verifyEmail(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->json([
                'message' => 'Érvénytelen verifikációs link.',
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Az email címed már megerősítve van.',
                'already_verified' => true,
            ]);
        }

        $user->markEmailAsVerified();

        \Log::info('[Auth] Email verified', ['user_id' => $user->id]);

        return response()->json([
            'message' => 'Az email címed sikeresen megerősítve. Most már bejelentkezhetsz.',
        ]);
    }

    /**
     * Resend verification email
     */
    public function resendVerification(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        // Security: Always return success to prevent email enumeration
        if (! $user || $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Ha az email cím létezik és még nincs megerősítve, küldtünk egy új linket.',
            ]);
        }

        // Send verification email
        $this->sendVerificationEmail($user);

        return response()->json([
            'message' => 'Ha az email cím létezik és még nincs megerősítve, küldtünk egy új linket.',
        ]);
    }

    /**
     * Send verification email to user
     */
    private function sendVerificationEmail(User $user): void
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        try {
            Mail::send('emails.verification', [
                'user' => $user,
                'verificationUrl' => $verificationUrl,
            ], function ($message) use ($user) {
                $message->to($user->email, $user->name)
                    ->subject('Email cím megerősítése - Photo Stack');
            });

            \Log::info('[Auth] Verification email sent', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            \Log::error('[Auth] Failed to send verification email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ==========================================
    // QR REGISTRATION
    // ==========================================

    /**
     * Validate QR registration code
     */
    public function validateQrCode(string $code)
    {
        $result = $this->qrService->validateCode($code);

        if (! $result['valid']) {
            return response()->json([
                'valid' => false,
                'message' => $result['error'],
            ], 400);
        }

        $project = $result['project'];

        return response()->json([
            'valid' => true,
            'project' => [
                'id' => $project->id,
                'name' => $project->display_name,
                'schoolName' => $project->school?->name,
                'className' => $project->class_name,
                'classYear' => $project->class_year,
            ],
        ]);
    }

    /**
     * Register from QR code
     */
    public function registerFromQr(QrRegistrationRequest $request)
    {
        try {
            $result = $this->qrService->registerFromQr(
                code: $request->input('code'),
                name: $request->input('name'),
                email: $request->input('email'),
                phone: $request->input('phone'),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            $user = $result['user'];
            $session = $result['session'];
            $project = $result['project'];

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'type' => 'tablo-guest',
                    'passwordSet' => (bool) $user->password_set,
                ],
                'project' => [
                    'id' => $project->id,
                    'name' => $project->display_name,
                    'schoolName' => $project->school?->name,
                    'className' => $project->class_name,
                    'classYear' => $project->class_year,
                    'samplesCount' => $project->getMedia('samples')->count(),
                    'activePollsCount' => $project->polls()->active()->count(),
                ],
                'token' => $result['token'],
                'tokenType' => 'code',  // QR regisztráció = teljes jogú kódos belépés
                'canFinalize' => true,
                'guestSession' => [
                    'sessionToken' => $session->session_token,
                    'guestName' => $session->guest_name,
                    'guestEmail' => $session->guest_email,
                ],
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            \Log::error('[Auth] QR registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Hiba történt a regisztráció során.',
            ], 500);
        }
    }

    // ==========================================
    // SESSION MANAGEMENT
    // ==========================================

    /**
     * Get active sessions for current user
     */
    public function activeSessions(Request $request)
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()->id;

        $sessions = $this->authService->getActiveSessions($user)
            ->map(function ($session) use ($currentTokenId) {
                $session['is_current'] = $session['id'] === $currentTokenId;

                return $session;
            });

        return response()->json([
            'sessions' => $sessions,
        ]);
    }

    /**
     * Revoke a specific session
     */
    public function revokeSession(Request $request, int $tokenId)
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()->id;

        // Cannot revoke current session through this endpoint
        if ($tokenId === $currentTokenId) {
            return response()->json([
                'message' => 'Nem törölheted a jelenlegi munkamenetedet. Használd a kijelentkezést.',
            ], 400);
        }

        $success = $this->authService->revokeSession($user, $tokenId);

        if (! $success) {
            return response()->json([
                'message' => 'Munkamenet nem található.',
            ], 404);
        }

        return response()->json([
            'message' => 'Munkamenet sikeresen törölve.',
        ]);
    }

    /**
     * Revoke all sessions except current
     */
    public function revokeAllSessions(Request $request)
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()->id;

        $count = $this->authService->revokeAllSessions($user, $currentTokenId);

        return response()->json([
            'message' => "{$count} munkamenet sikeresen törölve.",
            'revoked_count' => $count,
        ]);
    }

    // ==========================================
    // 2FA (PREPARATION - NOT YET IMPLEMENTED)
    // ==========================================

    /**
     * Enable 2FA (not yet implemented)
     */
    public function enable2FA(Request $request)
    {
        if (! Setting::get('auth.two_factor_available', false)) {
            return response()->json([
                'message' => 'A kétfaktoros hitelesítés jelenleg nem elérhető.',
                'available' => false,
            ], 503);
        }

        // TODO: Implement 2FA
        return response()->json([
            'message' => 'A kétfaktoros hitelesítés hamarosan elérhető lesz.',
            'available' => false,
        ], 503);
    }

    /**
     * Confirm 2FA setup (not yet implemented)
     */
    public function confirm2FA(Request $request)
    {
        return response()->json([
            'message' => 'A kétfaktoros hitelesítés jelenleg nem elérhető.',
            'available' => false,
        ], 503);
    }

    /**
     * Disable 2FA (not yet implemented)
     */
    public function disable2FA(Request $request)
    {
        return response()->json([
            'message' => 'A kétfaktoros hitelesítés jelenleg nem elérhető.',
            'available' => false,
        ], 503);
    }

    /**
     * Verify 2FA code (not yet implemented)
     */
    public function verify2FA(Request $request)
    {
        return response()->json([
            'message' => 'A kétfaktoros hitelesítés jelenleg nem elérhető.',
            'available' => false,
        ], 503);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

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

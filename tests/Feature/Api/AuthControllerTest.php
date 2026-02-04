<?php

namespace Tests\Feature\Api;

use App\Enums\TabloProjectStatus;
use App\Models\PartnerClient;
use App\Models\TabloPartner;
use App\Models\TabloGuestSession;
use App\Models\TabloProject;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\AuthenticationService;
use App\Services\MagicLinkService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * AuthController Feature Tesztek
 *
 * Authentikációs API végpontok tesztelése.
 *
 * FONTOS: DatabaseTransactions használata RefreshDatabase HELYETT!
 */
class AuthControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected TabloProject $project;
    protected AuthenticationService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('SecurePass123!'),
            'email_verified_at' => now(),
            'password_set' => true,
        ]);
        $this->user->assignRole(User::ROLE_CUSTOMER);

        $this->project = TabloProject::factory()->create([
            'access_code' => '123456',
            'access_code_enabled' => true,
            'access_code_expires_at' => now()->addDays(30),
            'status' => TabloProjectStatus::Active,
            'share_token' => Str::random(64),
            'share_token_expires_at' => now()->addDays(30),
        ]);

        $this->authService = app(AuthenticationService::class);
    }

    // ==================== LOGIN EMAIL/PASSWORD TESZTEK ====================

    public function test_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'SecurePass123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'type',
                    'roles',
                    'passwordSet',
                ],
                'token',
            ]);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_with_invalid_password(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'WrongPassword123!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Hibás email vagy jelszó',
            ]);
    }

    public function test_login_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'SomePassword123!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Hibás email vagy jelszó',
            ]);
    }

    public function test_login_with_unverified_email_when_verification_required(): void
    {
        // Hozzunk létre egy nem megerősített felhasználót
        $unverifiedUser = User::factory()->create([
            'email' => 'unverified@example.com',
            'password' => Hash::make('SecurePass123!'),
            'email_verified_at' => null,
        ]);

        // Mock a Setting-et, hogy a verification required legyen
        \App\Models\Setting::set('auth.email_verification_required', true);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'unverified@example.com',
            'password' => 'SecurePass123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'email_not_verified' => true,
            ]);
    }

    public function test_login_validation_errors(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'invalid-email',
            'password' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    // ==================== LOGIN TABLO CODE TESZTEK ====================

    public function test_login_tablo_code_with_valid_code(): void
    {
        $response = $this->postJson('/api/auth/login-tablo-code', [
            'code' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'type',
                ],
                'project' => [
                    'id',
                    'name',
                    'schoolName',
                    'className',
                ],
                'token',
                'tokenType',
                'canFinalize',
                'guestSession',
                'loginType',
            ])
            ->assertJson([
                'tokenType' => 'code',
                'canFinalize' => true,
                'loginType' => 'tablo',
            ]);
    }

    public function test_login_tablo_code_with_invalid_code(): void
    {
        $response = $this->postJson('/api/auth/login-tablo-code', [
            'code' => '999999',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Érvénytelen belépési kód',
            ]);
    }

    public function test_login_tablo_code_with_disabled_code(): void
    {
        $this->project->update(['access_code_enabled' => false]);

        $response = $this->postJson('/api/auth/login-tablo-code', [
            'code' => '123456',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'A belépési kód le van tiltva',
            ]);
    }

    public function test_login_tablo_code_with_expired_code(): void
    {
        $this->project->update(['access_code_expires_at' => now()->subDay()]);

        $response = $this->postJson('/api/auth/login-tablo-code', [
            'code' => '123456',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'A belépési kód lejárt',
            ]);
    }

    public function test_login_tablo_code_with_closed_project(): void
    {
        $this->project->update(['status' => TabloProjectStatus::Done]);

        $response = $this->postJson('/api/auth/login-tablo-code', [
            'code' => '123456',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Ez a projekt már lezárult',
            ]);
    }

    // ==================== LOGIN SHARE TOKEN TESZTEK ====================

    public function test_login_share_token_with_valid_token(): void
    {
        $response = $this->postJson('/api/auth/login-tablo-share', [
            'token' => $this->project->share_token,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'type',
                ],
                'project' => [
                    'id',
                    'name',
                ],
                'token',
                'tokenType',
                'canFinalize',
            ])
            ->assertJson([
                'tokenType' => 'share',
                'canFinalize' => false, // Share token nem véglegesíthet
            ]);
    }

    public function test_login_share_token_with_invalid_token(): void
    {
        $response = $this->postJson('/api/auth/login-tablo-share', [
            'token' => Str::random(64),
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Érvénytelen vagy lejárt megosztási link',
            ]);
    }

    public function test_login_share_token_with_closed_project(): void
    {
        $this->project->update(['status' => TabloProjectStatus::Done]);

        $response = $this->postJson('/api/auth/login-tablo-share', [
            'token' => $this->project->share_token,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Ez a projekt már lezárult',
            ]);
    }

    public function test_login_share_token_with_restore_session(): void
    {
        // Létrehozunk egy verified guest session-t restore tokennel
        $guestSession = TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Teszt Vendég',
            'guest_email' => 'vendeg@example.com',
            'restore_token' => $restoreToken = Str::random(64),
            'restore_token_expires_at' => now()->addDay(),
            'email_verified_at' => now(),
            'last_activity_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/login-tablo-share', [
            'token' => $this->project->share_token,
            'restore' => $restoreToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'restoredSession' => [
                    'sessionToken',
                    'guestName',
                    'guestEmail',
                ],
            ]);

        // Ellenőrizzük, hogy a restore token invalidálva lett
        $this->assertNull($guestSession->fresh()->restore_token);
    }

    // ==================== LOGIN PREVIEW TOKEN TESZTEK ====================

    public function test_login_preview_token_with_valid_token(): void
    {
        $previewToken = Str::random(64);
        $this->project->update([
            'admin_preview_token' => $previewToken,
            'admin_preview_token_expires_at' => now()->addHour(),
        ]);

        $response = $this->postJson('/api/auth/login-tablo-preview', [
            'token' => $previewToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user',
                'project',
                'token',
                'isPreview',
                'tokenType',
                'canFinalize',
            ])
            ->assertJson([
                'isPreview' => true,
                'tokenType' => 'preview',
                'canFinalize' => false,
            ]);

        // Ellenőrizzük, hogy a token invalidálva lett (egyszer használatos)
        $this->assertNull($this->project->fresh()->admin_preview_token);
    }

    public function test_login_preview_token_with_invalid_token(): void
    {
        $response = $this->postJson('/api/auth/login-tablo-preview', [
            'token' => Str::random(64),
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Érvénytelen vagy lejárt előnézeti link',
            ]);
    }

    // ==================== LOGIN WORK SESSION CODE TESZTEK ====================

    public function test_login_code_with_valid_work_session(): void
    {
        $workSession = WorkSession::factory()->create([
            'digit_code' => '654321',
            'digit_code_enabled' => true,
            'digit_code_expires_at' => now()->addDays(7),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/auth/login-code', [
            'code' => '654321',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'type',
                    'workSessionId',
                    'workSessionName',
                ],
                'token',
            ])
            ->assertJson([
                'user' => [
                    'type' => 'guest',
                ],
            ]);
    }

    public function test_login_code_with_invalid_code(): void
    {
        $response = $this->postJson('/api/auth/login-code', [
            'code' => '000000',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Ez a munkamenet már megszűnt vagy lejárt',
            ]);
    }

    public function test_login_code_with_disabled_code(): void
    {
        WorkSession::factory()->create([
            'digit_code' => '654321',
            'digit_code_enabled' => false,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/auth/login-code', [
            'code' => '654321',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'A belépési kód le van tiltva',
            ]);
    }

    public function test_login_code_with_expired_code(): void
    {
        WorkSession::factory()->create([
            'digit_code' => '654321',
            'digit_code_enabled' => true,
            'digit_code_expires_at' => now()->subDay(),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/auth/login-code', [
            'code' => '654321',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'A belépési kód lejárt',
            ]);
    }

    public function test_login_code_with_inactive_session(): void
    {
        WorkSession::factory()->create([
            'digit_code' => '654321',
            'digit_code_enabled' => true,
            'status' => 'closed',
        ]);

        $response = $this->postJson('/api/auth/login-code', [
            'code' => '654321',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Ez a munkamenet már nem elérhető',
            ]);
    }

    // ==================== REGISTER TESZTEK ====================

    public function test_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Új Felhasználó',
            'email' => 'new@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'phone' => '+36301234567',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'name' => 'Új Felhasználó',
        ]);
    }

    public function test_register_with_existing_email(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Másik Felhasználó',
            'email' => 'test@example.com', // Már létezik a setUp-ban
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_with_weak_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Új Felhasználó',
            'email' => 'weak@example.com',
            'password' => '12345678', // Túl gyenge
            'password_confirmation' => '12345678',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    // ==================== LOGOUT TESZTEK ====================

    public function test_logout_revokes_token(): void
    {
        // Bejelentkezés tokenért
        $token = $this->user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Sikeresen kijelentkeztél',
            ]);

        // Ellenőrizzük, hogy a token törölve lett
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->user->id,
            'name' => 'auth-token',
        ]);
    }

    public function test_logout_without_token_returns_401(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    // ==================== FORGOT PASSWORD TESZTEK ====================

    public function test_forgot_password_sends_email(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Ha az email cím létezik, küldtünk egy jelszó-visszaállítási linket.',
            ]);

        // Token létrejött
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_forgot_password_with_nonexistent_email(): void
    {
        // Security: ugyanazt a választ adjuk, mint létező emailnél
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Ha az email cím létezik, küldtünk egy jelszó-visszaállítási linket.',
            ]);
    }

    // ==================== RESET PASSWORD TESZTEK ====================

    public function test_reset_password_with_valid_token(): void
    {
        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email' => 'test@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'NewSecurePass123!',
            'password_confirmation' => 'NewSecurePass123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'A jelszavad sikeresen megváltozott. Most már bejelentkezhetsz.',
            ]);

        // Token törölve lett
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'test@example.com',
        ]);

        // Új jelszóval be lehet lépni
        $this->assertTrue(Hash::check('NewSecurePass123!', $this->user->fresh()->password));
    }

    public function test_reset_password_with_invalid_token(): void
    {
        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email' => 'test@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'test@example.com',
            'token' => 'wrong-token',
            'password' => 'NewSecurePass123!',
            'password_confirmation' => 'NewSecurePass123!',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Érvénytelen vagy lejárt visszaállítási link.',
            ]);
    }

    public function test_reset_password_with_expired_token(): void
    {
        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email' => 'test@example.com',
            'token' => Hash::make($token),
            'created_at' => now()->subHours(2), // 2 órával régebbi (1 óra a limit)
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'NewSecurePass123!',
            'password_confirmation' => 'NewSecurePass123!',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'A visszaállítási link lejárt. Kérj új linket.',
            ]);
    }

    // ==================== SET PASSWORD TESZTEK ====================

    public function test_set_password_for_authenticated_user(): void
    {
        $userWithoutPassword = User::factory()->create([
            'password' => null,
            'password_set' => false,
        ]);

        $token = $userWithoutPassword->createToken('auth-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/auth/set-password', [
            'password' => 'NewSecurePass123!',
            'password_confirmation' => 'NewSecurePass123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Jelszó sikeresen beállítva',
            ]);

        $this->assertTrue($userWithoutPassword->fresh()->password_set);
    }

    public function test_set_password_validation_errors(): void
    {
        $token = $this->user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/auth/set-password', [
            'password' => 'weak', // Túl rövid, nincs speciális karakter
            'password_confirmation' => 'weak',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    // ==================== CHANGE PASSWORD TESZTEK ====================

    public function test_change_password_for_authenticated_user(): void
    {
        $token = $this->user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/auth/change-password', [
            'current_password' => 'SecurePass123!',
            'password' => 'NewSecurePass456!',
            'password_confirmation' => 'NewSecurePass456!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'A jelszavad sikeresen megváltozott.',
            ]);

        $this->assertTrue(Hash::check('NewSecurePass456!', $this->user->fresh()->password));
    }

    // ==================== REFRESH TOKEN TESZTEK ====================

    public function test_refresh_returns_current_user(): void
    {
        $token = $this->user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/auth/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'type',
                ],
            ])
            ->assertJson([
                'user' => [
                    'id' => $this->user->id,
                    'email' => 'test@example.com',
                ],
            ]);
    }

    public function test_refresh_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/auth/refresh');

        $response->assertStatus(401);
    }

    // ==================== MAGIC LINK TESZTEK ====================

    public function test_request_magic_link_sends_email(): void
    {
        $response = $this->postJson('/api/auth/magic-link', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_request_magic_link_with_nonexistent_email(): void
    {
        // Security: ugyanazt a választ adjuk
        $response = $this->postJson('/api/auth/magic-link', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_validate_magic_token(): void
    {
        $magicLinkService = app(MagicLinkService::class);
        $token = $magicLinkService->generate($this->user->id);

        $response = $this->getJson("/api/auth/magic/{$token}/validate");

        $response->assertStatus(200)
            ->assertJson([
                'valid' => true,
                'user' => [
                    'id' => $this->user->id,
                ],
            ]);
    }

    public function test_validate_invalid_magic_token(): void
    {
        $response = $this->getJson('/api/auth/magic/invalid-token/validate');

        $response->assertStatus(401)
            ->assertJson([
                'valid' => false,
            ]);
    }

    public function test_login_with_magic_token(): void
    {
        $magicLinkService = app(MagicLinkService::class);
        $token = $magicLinkService->generate($this->user->id);

        $response = $this->postJson("/api/auth/magic/{$token}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ])
            ->assertJson([
                'message' => 'Sikeres bejelentkezés',
                'user' => [
                    'id' => $this->user->id,
                ],
            ]);
    }

    public function test_magic_token_is_consumed_after_use(): void
    {
        $magicLinkService = app(MagicLinkService::class);
        $token = $magicLinkService->generate($this->user->id);

        // Első használat sikeres
        $this->postJson("/api/auth/magic/{$token}")->assertStatus(200);

        // Második használat sikertelen (token elhasználódott)
        $this->postJson("/api/auth/magic/{$token}")->assertStatus(401);
    }

    // ==================== SESSION MANAGEMENT TESZTEK ====================

    public function test_active_sessions_returns_list(): void
    {
        // Hozzunk létre több tokent
        $this->user->createToken('auth-token');
        $this->user->createToken('auth-token-2');
        $currentToken = $this->user->createToken('current-token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $currentToken->plainTextToken,
        ])->getJson('/api/auth/sessions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'sessions' => [
                    '*' => [
                        'id',
                        'name',
                        'created_at',
                        'is_current',
                    ],
                ],
            ]);

        // Ellenőrizzük, hogy a jelenlegi session meg van jelölve
        $sessions = collect($response->json('sessions'));
        $currentSession = $sessions->firstWhere('is_current', true);
        $this->assertNotNull($currentSession);
    }

    public function test_revoke_session(): void
    {
        $tokenToRevoke = $this->user->createToken('to-revoke');
        $currentToken = $this->user->createToken('current');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $currentToken->plainTextToken,
        ])->deleteJson("/api/auth/sessions/{$tokenToRevoke->accessToken->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Munkamenet sikeresen törölve.',
            ]);

        // Token törölve lett
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenToRevoke->accessToken->id,
        ]);
    }

    public function test_cannot_revoke_current_session(): void
    {
        $currentToken = $this->user->createToken('current');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $currentToken->plainTextToken,
        ])->deleteJson("/api/auth/sessions/{$currentToken->accessToken->id}");

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Nem törölheted a jelenlegi munkamenetedet. Használd a kijelentkezést.',
            ]);
    }

    public function test_revoke_all_sessions_except_current(): void
    {
        $this->user->createToken('token-1');
        $this->user->createToken('token-2');
        $this->user->createToken('token-3');
        $currentToken = $this->user->createToken('current');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $currentToken->plainTextToken,
        ])->deleteJson('/api/auth/sessions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'revoked_count',
            ]);

        // Csak a jelenlegi token maradt meg
        $this->assertEquals(1, $this->user->tokens()->count());
    }

    // ==================== EMAIL VERIFICATION TESZTEK ====================

    public function test_verify_email_with_valid_link(): void
    {
        $unverifiedUser = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $hash = sha1($unverifiedUser->email);

        $response = $this->getJson("/api/auth/email/verify/{$unverifiedUser->id}/{$hash}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Az email címed sikeresen megerősítve. Most már bejelentkezhetsz.',
            ]);

        $this->assertNotNull($unverifiedUser->fresh()->email_verified_at);
    }

    public function test_verify_email_with_invalid_hash(): void
    {
        $unverifiedUser = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $response = $this->getJson("/api/auth/email/verify/{$unverifiedUser->id}/invalid-hash");

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Érvénytelen verifikációs link.',
            ]);
    }

    public function test_verify_already_verified_email(): void
    {
        $hash = sha1($this->user->email);

        $response = $this->getJson("/api/auth/email/verify/{$this->user->id}/{$hash}");

        $response->assertStatus(200)
            ->assertJson([
                'already_verified' => true,
            ]);
    }

    public function test_resend_verification_email(): void
    {
        $unverifiedUser = User::factory()->create([
            'email' => 'unverified2@example.com',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/auth/email/resend', [
            'email' => 'unverified2@example.com',
        ]);

        $response->assertStatus(200);
    }

    // ==================== QR REGISTRATION TESZTEK ====================

    public function test_validate_qr_code(): void
    {
        $this->project->update([
            'qr_code' => 'QR123456',
            'qr_code_enabled' => true,
        ]);

        $response = $this->getJson('/api/auth/qr/validate/QR123456');

        $response->assertStatus(200)
            ->assertJson([
                'valid' => true,
                'project' => [
                    'id' => $this->project->id,
                ],
            ]);
    }

    public function test_validate_invalid_qr_code(): void
    {
        $response = $this->getJson('/api/auth/qr/validate/INVALID');

        $response->assertStatus(400)
            ->assertJson([
                'valid' => false,
            ]);
    }

    public function test_register_from_qr(): void
    {
        $this->project->update([
            'qr_code' => 'QR123456',
            'qr_code_enabled' => true,
        ]);

        $response = $this->postJson('/api/auth/qr/register', [
            'code' => 'QR123456',
            'name' => 'QR Felhasználó',
            'email' => 'qr@example.com',
            'phone' => '+36301234567',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user',
                'project',
                'token',
                'tokenType',
                'guestSession',
            ]);
    }

    // ==================== 2FA TESZTEK (NOT IMPLEMENTED) ====================

    public function test_2fa_returns_not_available(): void
    {
        $token = $this->user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/auth/2fa/enable');

        $response->assertStatus(503)
            ->assertJson([
                'available' => false,
            ]);
    }

    // ==================== PARTNER CLIENT LOGIN TESZTEK ====================

    public function test_login_as_partner_client_with_valid_code(): void
    {
        $partner = TabloPartner::factory()->withClientOrders()->create();

        $client = PartnerClient::factory()->create([
            'tablo_partner_id' => $partner->id,
            'access_code' => 'CLIENT01',
            'access_code_enabled' => true,
            'access_code_expires_at' => now()->addDays(30),
            'is_registered' => false,
        ]);

        $response = $this->postJson('/api/auth/login-tablo-code', [
            'code' => 'CLIENT01',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user',
                'client',
                'albums',
                'token',
                'tokenType',
                'loginType',
            ])
            ->assertJson([
                'tokenType' => 'client',
                'loginType' => 'client',
            ]);
    }

    public function test_login_as_registered_partner_client_requires_password(): void
    {
        $partner = TabloPartner::factory()->withClientOrders()->create();

        $client = PartnerClient::factory()->create([
            'tablo_partner_id' => $partner->id,
            'access_code' => 'CLIENT02',
            'access_code_enabled' => true,
            'is_registered' => true, // Már regisztrált
            'email' => 'client@example.com',
        ]);

        $response = $this->postJson('/api/auth/login-tablo-code', [
            'code' => 'CLIENT02',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'requiresPasswordLogin' => true,
                'email' => 'client@example.com',
            ]);
    }
}

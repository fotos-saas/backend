<?php

namespace Tests\Feature\Api\Tablo;

use App\Models\TabloGuestSession;
use App\Models\TabloProject;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GuestSessionController Feature Tesztek
 *
 * Vendég session kezelés API végpontok tesztelése.
 *
 * FONTOS: DatabaseTransactions használata RefreshDatabase HELYETT!
 */
class GuestSessionControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected TabloProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = TabloProject::factory()->create();
    }

    /**
     * Helper: Token létrehozása és authentikáció beállítása
     */
    protected function createTokenWithType(string $tokenType): void
    {
        $token = $this->user->createToken($tokenType);
        $token->accessToken->tablo_project_id = $this->project->id;
        $token->accessToken->save();

        $this->actingAs($this->user->withAccessToken($token->accessToken), 'sanctum');
    }

    // ==================== REGISTER TESZTEK ====================

    public function test_register_creates_guest_session(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->postJson('/api/tablo-frontend/guest/register', [
            'guest_name' => 'Teszt Vendég',
            'guest_email' => 'teszt@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Sikeres regisztráció!',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'session_token',
                    'guest_name',
                    'guest_email',
                ],
            ]);

        $this->assertDatabaseHas('tablo_guest_sessions', [
            'tablo_project_id' => $this->project->id,
            'guest_name' => 'Teszt Vendég',
            'guest_email' => 'teszt@example.com',
        ]);
    }

    public function test_register_validates_guest_name(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        // Üres név
        $response = $this->postJson('/api/tablo-frontend/guest/register', [
            'guest_name' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['guest_name']);
    }

    public function test_register_validates_minimum_name_length(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->postJson('/api/tablo-frontend/guest/register', [
            'guest_name' => 'A', // Túl rövid
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['guest_name']);
    }

    public function test_register_validates_email_format(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->postJson('/api/tablo-frontend/guest/register', [
            'guest_name' => 'Teszt Vendég',
            'guest_email' => 'invalid-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['guest_email']);
    }

    public function test_register_allows_optional_email(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->postJson('/api/tablo-frontend/guest/register', [
            'guest_name' => 'Teszt Vendég',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_register_returns_404_for_invalid_project(): void
    {
        $token = $this->user->createToken('tablo-auth-token');
        $token->accessToken->tablo_project_id = 99999;
        $token->accessToken->save();

        $this->actingAs($this->user->withAccessToken($token->accessToken), 'sanctum');

        $response = $this->postJson('/api/tablo-frontend/guest/register', [
            'guest_name' => 'Teszt Vendég',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Projekt nem található',
            ]);
    }

    // ==================== VALIDATE TESZTEK ====================

    public function test_validate_confirms_valid_session(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $session = TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Teszt Vendég',
        ]);

        $response = $this->postJson('/api/tablo-frontend/guest/validate', [
            'session_token' => $session->session_token,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'valid' => true,
                'data' => [
                    'guest_name' => 'Teszt Vendég',
                ],
            ]);
    }

    public function test_validate_rejects_invalid_session(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->postJson('/api/tablo-frontend/guest/validate', [
            'session_token' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'valid' => false,
            ]);
    }

    public function test_validate_requires_uuid_format(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->postJson('/api/tablo-frontend/guest/validate', [
            'session_token' => 'not-a-uuid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['session_token']);
    }

    // ==================== SEND LINK TESZTEK ====================

    public function test_send_link_sends_device_link(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $session = TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Teszt Vendég',
        ]);

        $response = $this->postJson('/api/tablo-frontend/guest/send-link', [
            'session_token' => $session->session_token,
            'email' => 'teszt@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Link elküldve a megadott email címre!',
            ]);
    }

    public function test_send_link_updates_guest_email(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $session = TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Teszt Vendég',
            'guest_email' => 'regi@example.com',
        ]);

        $this->postJson('/api/tablo-frontend/guest/send-link', [
            'session_token' => $session->session_token,
            'email' => 'uj@example.com',
        ]);

        $session->refresh();
        $this->assertEquals('uj@example.com', $session->guest_email);
    }

    public function test_send_link_validates_email(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $session = TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Teszt Vendég',
        ]);

        $response = $this->postJson('/api/tablo-frontend/guest/send-link', [
            'session_token' => $session->session_token,
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_send_link_returns_404_for_invalid_session(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->postJson('/api/tablo-frontend/guest/send-link', [
            'session_token' => Str::uuid()->toString(),
            'email' => 'teszt@example.com',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Session nem található',
            ]);
    }

    // ==================== HEARTBEAT TESZTEK ====================

    public function test_heartbeat_updates_activity(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $session = TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Teszt Vendég',
            'last_activity_at' => now()->subHour(),
        ]);

        $oldActivity = $session->last_activity_at;

        $response = $this->postJson('/api/tablo-frontend/guest/heartbeat', [
            'session_token' => $session->session_token,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $session->refresh();
        $this->assertTrue($session->last_activity_at->greaterThan($oldActivity));
    }

    public function test_heartbeat_returns_404_for_invalid_session(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->postJson('/api/tablo-frontend/guest/heartbeat', [
            'session_token' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(404);
    }

    // ==================== GET GUESTS TESZTEK ====================

    public function test_get_guests_returns_guest_list(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Vendég 1',
        ]);

        TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Vendég 2',
        ]);

        $response = $this->getJson('/api/tablo-frontend/admin/guests');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'guest_name',
                        'guest_email',
                        'is_banned',
                        'last_activity_at',
                        'created_at',
                    ],
                ],
                'statistics',
            ]);
    }

    public function test_get_guests_filters_banned(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Aktív Vendég',
            'is_banned' => false,
        ]);

        TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Tiltott Vendég',
            'is_banned' => true,
        ]);

        $response = $this->getJson('/api/tablo-frontend/admin/guests?include_banned=false');

        $response->assertStatus(200);
        $data = $response->json('data');

        foreach ($data as $guest) {
            $this->assertFalse($guest['is_banned']);
        }
    }

    // ==================== BAN/UNBAN TESZTEK ====================

    public function test_ban_bans_guest(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $session = TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Teszt Vendég',
            'is_banned' => false,
        ]);

        $response = $this->postJson("/api/tablo-frontend/admin/guests/{$session->id}/ban");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Vendég sikeresen tiltva!',
            ]);

        $session->refresh();
        $this->assertTrue($session->is_banned);
    }

    public function test_ban_returns_404_for_invalid_guest(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->postJson('/api/tablo-frontend/admin/guests/99999/ban');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Vendég nem található',
            ]);
    }

    public function test_unban_unbans_guest(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $session = TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Teszt Vendég',
            'is_banned' => true,
        ]);

        $response = $this->postJson("/api/tablo-frontend/admin/guests/{$session->id}/unban");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Vendég tiltása feloldva!',
            ]);

        $session->refresh();
        $this->assertFalse($session->is_banned);
    }

    public function test_unban_returns_404_for_invalid_guest(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->postJson('/api/tablo-frontend/admin/guests/99999/unban');

        $response->assertStatus(404);
    }

    // ==================== SET CLASS SIZE TESZTEK ====================

    public function test_set_class_size_updates_project(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->putJson('/api/tablo-frontend/admin/class-size', [
            'expected_class_size' => 35,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Osztálylétszám sikeresen beállítva!',
                'data' => [
                    'expected_class_size' => 35,
                ],
            ]);

        $this->project->refresh();
        $this->assertEquals(35, $this->project->expected_class_size);
    }

    public function test_set_class_size_validates_minimum(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->putJson('/api/tablo-frontend/admin/class-size', [
            'expected_class_size' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['expected_class_size']);
    }

    public function test_set_class_size_validates_maximum(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->putJson('/api/tablo-frontend/admin/class-size', [
            'expected_class_size' => 1000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['expected_class_size']);
    }

    public function test_set_class_size_returns_404_for_invalid_project(): void
    {
        $token = $this->user->createToken('tablo-auth-token');
        $token->accessToken->tablo_project_id = 99999;
        $token->accessToken->save();

        $this->actingAs($this->user->withAccessToken($token->accessToken), 'sanctum');

        $response = $this->putJson('/api/tablo-frontend/admin/class-size', [
            'expected_class_size' => 35,
        ]);

        $response->assertStatus(404);
    }

    // ==================== AUTHENTICATION TESZTEK ====================

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->postJson('/api/tablo-frontend/guest/register', [
            'guest_name' => 'Teszt',
        ]);

        $response->assertStatus(401);
    }
}

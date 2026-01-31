<?php

namespace Tests\Feature;

use App\Models\TabloProject;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * RequireFullAccess Middleware Tesztek
 *
 * A middleware biztosítja, hogy csak teljes jogosultsággal (kódos belépés)
 * rendelkező felhasználók módosíthassanak kritikus adatokat.
 *
 * Token típusok:
 * - tablo-auth-token: Teljes hozzáférés (kódos belépés) → ENGEDÉLYEZETT
 * - tablo-share-token: Vendég (share link) → TILTOTT (403)
 * - tablo-preview-token: Admin preview → TILTOTT (403)
 *
 * FONTOS: DatabaseTransactions használata RefreshDatabase HELYETT!
 * A RefreshDatabase TÖRLI az adatbázist - SOHA NE HASZNÁLD!
 */
class RequireFullAccessMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected TabloProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Felhasználó és projekt létrehozása
        $this->user = User::factory()->create();
        $this->project = TabloProject::factory()->create();
    }

    /**
     * Helper: Token létrehozása adott típussal és beállítja az authentikációt
     */
    protected function createTokenWithType(string $tokenType): void
    {
        $token = $this->user->createToken($tokenType);
        $token->accessToken->tablo_project_id = $this->project->id;
        $token->accessToken->save();

        // KRITIKUS: withAccessToken() kell a currentAccessToken() működéséhez!
        $this->actingAs($this->user->withAccessToken($token->accessToken), 'sanctum');
    }

    /**
     * Teszt: Teljes jogú felhasználó (code token) sikeresen kiválaszthat mintát
     */
    public function test_full_access_user_can_select_template(): void
    {
        // Kódos belépéssel rendelkező felhasználó
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->postJson('/api/tablo-frontend/templates/1/select');

        // Mivel nincs valódi template, 404-et várunk, de NEM 403-at!
        // Ez azt jelenti, hogy a middleware engedélyezte a kérést
        $this->assertNotEquals(403, $response->status());
    }

    /**
     * Teszt: Vendég felhasználó (share token) nem választhat ki mintát
     */
    public function test_guest_user_cannot_select_template(): void
    {
        // Vendég (share link) felhasználó
        $this->createTokenWithType('tablo-share-token');

        $response = $this->postJson('/api/tablo-frontend/templates/1/select');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => 'insufficient_permissions',
            ])
            ->assertJsonFragment([
                'message' => 'Ez a művelet csak teljes jogosultsággal érhető el. Megosztott vagy előnézeti linkkel nem lehetséges.',
            ]);
    }

    /**
     * Teszt: Preview felhasználó (preview token) nem választhat ki mintát
     */
    public function test_preview_user_cannot_select_template(): void
    {
        // Admin preview felhasználó
        $this->createTokenWithType('tablo-preview-token');

        $response = $this->postJson('/api/tablo-frontend/templates/1/select');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => 'insufficient_permissions',
            ]);
    }

    /**
     * Teszt: Teljes jogú felhasználó frissítheti az ütemezést
     */
    public function test_full_access_user_can_update_schedule(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->postJson('/api/tablo-frontend/update-schedule', [
            'photo_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        // A middleware engedélyezi, nincs 403
        $this->assertNotEquals(403, $response->status());
    }

    /**
     * Teszt: Vendég felhasználó nem frissítheti az ütemezést
     */
    public function test_guest_user_cannot_update_schedule(): void
    {
        $this->createTokenWithType('tablo-share-token');

        $response = $this->postJson('/api/tablo-frontend/update-schedule', [
            'photo_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => 'insufficient_permissions',
            ]);
    }

    /**
     * Teszt: Teljes jogú felhasználó módosíthatja a kapcsolattartót
     */
    public function test_full_access_user_can_update_contact(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->putJson('/api/tablo-frontend/contact', [
            'name' => 'Teszt Kapcsolattartó',
            'email' => 'teszt@example.com',
            'phone' => '+36301234567',
        ]);

        // A middleware engedélyezi
        $this->assertNotEquals(403, $response->status());
    }

    /**
     * Teszt: Preview felhasználó nem módosíthatja a kapcsolattartót
     */
    public function test_preview_user_cannot_update_contact(): void
    {
        $this->createTokenWithType('tablo-preview-token');

        $response = $this->putJson('/api/tablo-frontend/contact', [
            'name' => 'Teszt Kapcsolattartó',
            'email' => 'teszt@example.com',
            'phone' => '+36301234567',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => 'insufficient_permissions',
            ]);
    }

    /**
     * Teszt: Teljes jogú felhasználó törölhet mintát
     */
    public function test_full_access_user_can_deselect_template(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->deleteJson('/api/tablo-frontend/templates/1/select');

        // Mivel nincs valódi template, nem 403-at várunk
        $this->assertNotEquals(403, $response->status());
    }

    /**
     * Teszt: Vendég felhasználó nem törölhet mintát
     */
    public function test_guest_user_cannot_deselect_template(): void
    {
        $this->createTokenWithType('tablo-share-token');

        $response = $this->deleteJson('/api/tablo-frontend/templates/1/select');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => 'insufficient_permissions',
            ]);
    }

    /**
     * Teszt: A hibaüzenet magyar nyelven jelenik meg
     */
    public function test_error_message_is_in_hungarian(): void
    {
        $this->createTokenWithType('tablo-share-token');

        $response = $this->postJson('/api/tablo-frontend/templates/1/select');

        $response->assertStatus(403);

        $responseData = $response->json();
        $message = $responseData['message'] ?? '';

        // Magyar karakterek és kifejezések ellenőrzése
        $this->assertStringContainsString('művelet', $message);
        $this->assertStringContainsString('jogosultsággal', $message);
        $this->assertStringContainsString('érhető', $message);
    }

    /**
     * Teszt: Nem védett route-ok továbbra is elérhetőek minden token típusnak
     */
    public function test_unprotected_routes_accessible_with_any_token(): void
    {
        // Vendég token
        $this->createTokenWithType('tablo-share-token');

        // Projekt info - nem védett, olvasható vendégként is
        $response = $this->getJson('/api/tablo-frontend/project-info');
        $this->assertNotEquals(403, $response->status());

        // Samples - nem védett
        $response = $this->getJson('/api/tablo-frontend/samples');
        $this->assertNotEquals(403, $response->status());

        // Templates lista - nem védett
        $response = $this->getJson('/api/tablo-frontend/templates');
        $this->assertNotEquals(403, $response->status());
    }

    /**
     * Teszt: Preview tokennel is elérhetőek az olvasási route-ok
     */
    public function test_preview_token_can_access_read_only_routes(): void
    {
        $this->createTokenWithType('tablo-preview-token');

        // Projekt info
        $response = $this->getJson('/api/tablo-frontend/project-info');
        $this->assertNotEquals(403, $response->status());

        // Order data
        $response = $this->getJson('/api/tablo-frontend/order-data');
        $this->assertNotEquals(403, $response->status());

        // Template kategóriák
        $response = $this->getJson('/api/tablo-frontend/templates/categories');
        $this->assertNotEquals(403, $response->status());
    }

    /**
     * Teszt: Minden védett írási műveletet blokkol a middleware vendég felhasználónál
     */
    public function test_all_write_operations_blocked_for_guest(): void
    {
        $this->createTokenWithType('tablo-share-token');

        // Template kiválasztás
        $this->postJson('/api/tablo-frontend/templates/1/select')
            ->assertStatus(403);

        // Template törlés
        $this->deleteJson('/api/tablo-frontend/templates/1/select')
            ->assertStatus(403);

        // Kapcsolattartó módosítás
        $this->putJson('/api/tablo-frontend/contact', [
            'name' => 'Test',
            'email' => 'test@test.com',
        ])->assertStatus(403);

        // Ütemezés frissítés
        $this->postJson('/api/tablo-frontend/update-schedule', [
            'photo_date' => now()->addDays(5)->format('Y-m-d'),
        ])->assertStatus(403);
    }

    /**
     * Teszt: Token nélküli kérések 401-et kapnak (nem 403-at)
     */
    public function test_unauthenticated_request_returns_401(): void
    {
        // Nincs token
        $response = $this->postJson('/api/tablo-frontend/templates/1/select');

        // Sanctum middleware már visszaadja a 401-et
        $response->assertStatus(401);
    }

    /**
     * Teszt: Érvényes token, de hibás token típus esetén 403
     */
    public function test_invalid_token_type_returns_403(): void
    {
        // Ismeretlen token típus létrehozása
        $this->createTokenWithType('invalid-token-type');

        $response = $this->postJson('/api/tablo-frontend/templates/1/select');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => 'insufficient_permissions',
            ]);
    }

    /**
     * Teszt: Priority frissítés csak teljes jogosultsággal
     */
    public function test_full_access_user_can_update_template_priority(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->patchJson('/api/tablo-frontend/templates/1/priority', [
            'priority' => 1,
        ]);

        // Middleware engedélyezi
        $this->assertNotEquals(403, $response->status());
    }

    /**
     * Teszt: Priority frissítés vendég tokennel tiltott
     */
    public function test_guest_user_cannot_update_template_priority(): void
    {
        $this->createTokenWithType('tablo-share-token');

        $response = $this->patchJson('/api/tablo-frontend/templates/1/priority', [
            'priority' => 1,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => 'insufficient_permissions',
            ]);
    }
}

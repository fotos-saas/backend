<?php

namespace Tests\Feature\Api\Partner;

use App\Enums\TabloProjectStatus;
use App\Models\Partner;
use App\Models\TabloContact;
use App\Models\TabloPerson;
use App\Models\TabloPartner;
use App\Models\TabloProject;
use App\Models\TabloSchool;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * PartnerProjectController Feature Tesztek
 *
 * Partner projekt CRUD műveletek tesztelése.
 *
 * FONTOS: DatabaseTransactions használata RefreshDatabase HELYETT!
 */
class PartnerProjectControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected TabloPartner $tabloPartner;
    protected Partner $partner;
    protected TabloSchool $school;

    protected function setUp(): void
    {
        parent::setUp();

        // Create TabloPartner first
        $this->tabloPartner = TabloPartner::factory()->create();

        // Create user with tablo_partner_id
        $this->user = User::factory()->create([
            'tablo_partner_id' => $this->tabloPartner->id,
        ]);
        $this->user->assignRole('partner');

        // Create Partner linked to user (for limit checks)
        $this->partner = Partner::create([
            'user_id' => $this->user->id,
            'company_name' => 'Test Studio Kft.',
            'plan' => 'alap',
            'max_classes' => 10,
        ]);

        // Create a test school
        $this->school = TabloSchool::create([
            'name' => 'Test Iskola',
            'city' => 'Budapest',
            'address' => 'Test utca 1.',
        ]);
    }

    /**
     * Helper: Token létrehozása és authentikáció beállítása
     */
    protected function actingAsPartner(): void
    {
        $token = $this->user->createToken('auth-token');
        $this->actingAs($this->user->withAccessToken($token->accessToken), 'sanctum');
    }

    /**
     * Helper: Projekt létrehozása a partner számára
     */
    protected function createProjectForPartner(array $overrides = []): TabloProject
    {
        return TabloProject::create(array_merge([
            'partner_id' => $this->tabloPartner->id,
            'school_id' => $this->school->id,
            'class_name' => 'Test Osztály',
            'class_year' => '2024/2025',
            'status' => TabloProjectStatus::Active,
            'expected_class_size' => 30,
        ], $overrides));
    }

    // ==================== STORE PROJECT TESZTEK ====================

    public function test_store_project_requires_auth(): void
    {
        $response = $this->postJson('/api/partner/projects');

        $response->assertStatus(401);
    }

    public function test_store_project_requires_partner(): void
    {
        // User partner nélkül
        $userWithoutPartner = User::factory()->create([
            'tablo_partner_id' => null,
        ]);
        $token = $userWithoutPartner->createToken('auth-token');
        $this->actingAs($userWithoutPartner->withAccessToken($token->accessToken), 'sanctum');

        $response = $this->postJson('/api/partner/projects', [
            'class_name' => 'Test Osztály',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_project_validation_errors(): void
    {
        $this->actingAsPartner();

        $response = $this->postJson('/api/partner/projects', [
            'school_id' => 99999, // Nem létező iskola
            'class_name' => str_repeat('a', 300), // Túl hosszú
            'expected_class_size' => 1000, // Túl nagy
            'contact_email' => 'invalid-email',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validációs hiba',
            ])
            ->assertJsonStructure([
                'errors' => [
                    'school_id',
                    'class_name',
                    'expected_class_size',
                    'contact_email',
                ],
            ]);
    }

    public function test_store_project_successful(): void
    {
        $this->actingAsPartner();

        $response = $this->postJson('/api/partner/projects', [
            'school_id' => $this->school->id,
            'class_name' => '12.A',
            'class_year' => '2024/2025',
            'photo_date' => '2024-09-15',
            'deadline' => '2024-10-15',
            'expected_class_size' => 28,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Projekt sikeresen létrehozva',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'schoolName',
                    'className',
                    'classYear',
                    'status',
                    'statusLabel',
                    'photoDate',
                    'deadline',
                    'expectedClassSize',
                ],
            ]);

        $this->assertDatabaseHas('tablo_projects', [
            'partner_id' => $this->tabloPartner->id,
            'class_name' => '12.A',
            'expected_class_size' => 28,
        ]);
    }

    public function test_store_project_with_contact(): void
    {
        $this->actingAsPartner();

        $response = $this->postJson('/api/partner/projects', [
            'school_id' => $this->school->id,
            'class_name' => '12.B',
            'class_year' => '2024/2025',
            'contact_name' => 'Kovács János',
            'contact_email' => 'kovacs@example.com',
            'contact_phone' => '+36301234567',
        ]);

        $response->assertStatus(201);

        // Ellenőrizzük, hogy a kapcsolattartó létrejött
        $this->assertDatabaseHas('tablo_contacts', [
            'partner_id' => $this->tabloPartner->id,
            'name' => 'Kovács János',
            'email' => 'kovacs@example.com',
        ]);
    }

    public function test_store_project_respects_plan_limit(): void
    {
        $this->actingAsPartner();

        // Állítsuk be a limitet 1-re
        $this->partner->update(['max_classes' => 1]);

        // Első projekt - sikeres
        $this->postJson('/api/partner/projects', [
            'class_name' => 'Első osztály',
        ])->assertStatus(201);

        // Második projekt - limit elérve
        $response = $this->postJson('/api/partner/projects', [
            'class_name' => 'Második osztály',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'upgrade_required' => true,
            ]);
    }

    // ==================== UPDATE PROJECT TESZTEK ====================

    public function test_update_project_requires_auth(): void
    {
        $project = $this->createProjectForPartner();

        $response = $this->putJson("/api/partner/projects/{$project->id}");

        $response->assertStatus(401);
    }

    public function test_update_project_not_found(): void
    {
        $this->actingAsPartner();

        $response = $this->putJson('/api/partner/projects/99999', [
            'class_name' => 'Updated Name',
        ]);

        $response->assertStatus(404);
    }

    public function test_update_project_forbidden_for_other_partner(): void
    {
        $this->actingAsPartner();

        // Másik partner projektje
        $otherPartner = TabloPartner::factory()->create();
        $otherProject = TabloProject::create([
            'partner_id' => $otherPartner->id,
            'class_name' => 'Other Project',
        ]);

        $response = $this->putJson("/api/partner/projects/{$otherProject->id}", [
            'class_name' => 'Hacked',
        ]);

        $response->assertStatus(404); // 404, nem 403, mert nem találja a saját projektjei között
    }

    public function test_update_project_validation_errors(): void
    {
        $this->actingAsPartner();
        $project = $this->createProjectForPartner();

        $response = $this->putJson("/api/partner/projects/{$project->id}", [
            'school_id' => 99999, // Nem létező
            'expected_class_size' => 0, // Min 1
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_update_project_successful(): void
    {
        $this->actingAsPartner();
        $project = $this->createProjectForPartner();

        $response = $this->putJson("/api/partner/projects/{$project->id}", [
            'class_name' => 'Updated Class Name',
            'class_year' => '2025/2026',
            'expected_class_size' => 35,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Projekt sikeresen módosítva',
            ]);

        $this->assertDatabaseHas('tablo_projects', [
            'id' => $project->id,
            'class_name' => 'Updated Class Name',
            'class_year' => '2025/2026',
            'expected_class_size' => 35,
        ]);
    }

    // ==================== DELETE PROJECT TESZTEK ====================

    public function test_delete_project_requires_auth(): void
    {
        $project = $this->createProjectForPartner();

        $response = $this->deleteJson("/api/partner/projects/{$project->id}");

        $response->assertStatus(401);
    }

    public function test_delete_project_not_found(): void
    {
        $this->actingAsPartner();

        $response = $this->deleteJson('/api/partner/projects/99999');

        $response->assertStatus(404);
    }

    public function test_delete_project_forbidden_for_other_partner(): void
    {
        $this->actingAsPartner();

        // Másik partner projektje
        $otherPartner = TabloPartner::factory()->create();
        $otherProject = TabloProject::create([
            'partner_id' => $otherPartner->id,
            'class_name' => 'Other Project',
        ]);

        $response = $this->deleteJson("/api/partner/projects/{$otherProject->id}");

        $response->assertStatus(404);
    }

    public function test_delete_project_successful(): void
    {
        $this->actingAsPartner();
        $project = $this->createProjectForPartner();

        $response = $this->deleteJson("/api/partner/projects/{$project->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Projekt sikeresen törölve',
            ]);

        // Soft delete ellenőrzés
        $this->assertSoftDeleted('tablo_projects', [
            'id' => $project->id,
        ]);
    }

    // ==================== TOGGLE AWARE TESZTEK ====================

    public function test_toggle_aware_requires_auth(): void
    {
        $project = $this->createProjectForPartner();

        $response = $this->postJson("/api/partner/projects/{$project->id}/toggle-aware");

        $response->assertStatus(401);
    }

    public function test_toggle_aware_successful(): void
    {
        $this->actingAsPartner();
        $project = $this->createProjectForPartner(['is_aware' => false]);

        // Első toggle: false -> true
        $response = $this->postJson("/api/partner/projects/{$project->id}/toggle-aware");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'isAware' => true,
            ]);

        $this->assertTrue($project->fresh()->is_aware);

        // Második toggle: true -> false
        $response = $this->postJson("/api/partner/projects/{$project->id}/toggle-aware");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'isAware' => false,
            ]);

        $this->assertFalse($project->fresh()->is_aware);
    }

    // ==================== PROJECT SAMPLES TESZTEK ====================

    public function test_project_samples_requires_auth(): void
    {
        $project = $this->createProjectForPartner();

        $response = $this->getJson("/api/partner/projects/{$project->id}/samples");

        $response->assertStatus(401);
    }

    public function test_project_samples_returns_empty_for_new_project(): void
    {
        $this->actingAsPartner();
        $project = $this->createProjectForPartner();

        $response = $this->getJson("/api/partner/projects/{$project->id}/samples");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
            ]);
    }

    // ==================== PROJECT MISSING PERSONS TESZTEK ====================

    public function test_project_missing_persons_requires_auth(): void
    {
        $project = $this->createProjectForPartner();

        $response = $this->getJson("/api/partner/projects/{$project->id}/missing-persons");

        $response->assertStatus(401);
    }

    public function test_project_missing_persons_returns_list(): void
    {
        $this->actingAsPartner();
        $project = $this->createProjectForPartner();

        // Hiányzó személyek hozzáadása
        TabloPerson::create([
            'tablo_project_id' => $project->id,
            'name' => 'Kiss Anna',
            'type' => 'student',
            'position' => 1,
        ]);

        TabloPerson::create([
            'tablo_project_id' => $project->id,
            'name' => 'Nagy Péter',
            'type' => 'student',
            'position' => 2,
        ]);

        $response = $this->getJson("/api/partner/projects/{$project->id}/missing-persons");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'type',
                        'hasPhoto',
                    ],
                ],
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_project_missing_persons_filter_without_photo(): void
    {
        $this->actingAsPartner();
        $project = $this->createProjectForPartner();

        // Hiányzó személy fotó nélkül
        TabloPerson::create([
            'tablo_project_id' => $project->id,
            'name' => 'Kiss Anna',
            'type' => 'student',
            'position' => 1,
            'media_id' => null,
        ]);

        // Hiányzó személy fotóval (mock media_id)
        TabloPerson::create([
            'tablo_project_id' => $project->id,
            'name' => 'Nagy Péter',
            'type' => 'student',
            'position' => 2,
            'media_id' => 1, // Van fotója
        ]);

        $response = $this->getJson("/api/partner/projects/{$project->id}/missing-persons?without_photo=true");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertEquals('Kiss Anna', $response->json('data.0.name'));
    }
}

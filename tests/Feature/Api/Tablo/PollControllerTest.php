<?php

namespace Tests\Feature\Api\Tablo;

use App\Models\TabloGuestSession;
use App\Models\TabloPoll;
use App\Models\TabloPollOption;
use App\Models\TabloPollVote;
use App\Models\TabloProject;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PollController Feature Tesztek
 *
 * Szavazás API végpontok tesztelése.
 *
 * FONTOS: DatabaseTransactions használata RefreshDatabase HELYETT!
 */
class PollControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected TabloProject $project;
    protected TabloGuestSession $guestSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = TabloProject::factory()->create([
            'expected_class_size' => 30,
        ]);
        $this->guestSession = TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Teszt Vendég',
            'guest_email' => 'teszt@example.com',
        ]);
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

    /**
     * Helper: Szavazás létrehozása opcióval
     */
    protected function createPollWithOptions(int $optionCount = 3, bool $isActive = true): TabloPoll
    {
        $poll = TabloPoll::create([
            'tablo_project_id' => $this->project->id,
            'title' => 'Teszt Szavazás',
            'description' => 'Teszt leírás',
            'type' => 'custom',
            'is_active' => $isActive,
            'is_multiple_choice' => false,
            'max_votes_per_guest' => 1,
        ]);

        for ($i = 1; $i <= $optionCount; $i++) {
            TabloPollOption::create([
                'tablo_poll_id' => $poll->id,
                'label' => "Opció $i",
                'display_order' => $i,
            ]);
        }

        return $poll->fresh();
    }

    // ==================== INDEX TESZTEK ====================

    public function test_index_returns_polls_list(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $this->createPollWithOptions();

        $response = $this->getJson('/api/tablo-frontend/polls');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'type',
                        'is_active',
                        'is_multiple_choice',
                        'is_open',
                        'total_votes',
                        'options_count',
                    ],
                ],
            ]);
    }

    public function test_index_filters_active_polls(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        // Aktív szavazás
        $this->createPollWithOptions(2, true);
        // Inaktív szavazás
        $this->createPollWithOptions(2, false);

        // Active only filter
        $response = $this->getJson('/api/tablo-frontend/polls?active_only=true');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    public function test_index_returns_guest_votes_with_session_header(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $poll = $this->createPollWithOptions();
        $option = $poll->options->first();

        // Szavazat leadása
        TabloPollVote::create([
            'tablo_poll_id' => $poll->id,
            'poll_option_id' => $option->id,
            'guest_session_id' => $this->guestSession->id,
            'voted_at' => now(),
        ]);

        $response = $this->withHeaders([
            'X-Guest-Session' => $this->guestSession->session_token,
        ])->getJson('/api/tablo-frontend/polls');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data[0]['my_votes']);
    }

    public function test_index_returns_404_for_invalid_project(): void
    {
        // Token másik projekthez
        $token = $this->user->createToken('tablo-auth-token');
        $token->accessToken->tablo_project_id = 99999;
        $token->accessToken->save();

        $this->actingAs($this->user->withAccessToken($token->accessToken), 'sanctum');

        $response = $this->getJson('/api/tablo-frontend/polls');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Projekt nem található',
            ]);
    }

    // ==================== SHOW TESZTEK ====================

    public function test_show_returns_poll_details(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $poll = $this->createPollWithOptions();

        $response = $this->getJson("/api/tablo-frontend/polls/{$poll->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $poll->id,
                    'title' => 'Teszt Szavazás',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'type',
                    'is_active',
                    'options',
                ],
            ]);
    }

    public function test_show_returns_404_for_nonexistent_poll(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->getJson('/api/tablo-frontend/polls/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Szavazás nem található',
            ]);
    }

    public function test_show_includes_can_vote_status(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $poll = $this->createPollWithOptions();

        $response = $this->withHeaders([
            'X-Guest-Session' => $this->guestSession->session_token,
        ])->getJson("/api/tablo-frontend/polls/{$poll->id}");

        $response->assertStatus(200);
        $this->assertArrayHasKey('can_vote', $response->json('data'));
    }

    // ==================== STORE TESZTEK ====================

    public function test_store_creates_new_poll(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->postJson('/api/tablo-frontend/polls', [
            'title' => 'Új Szavazás',
            'description' => 'Leírás',
            'type' => 'custom',
            'is_multiple_choice' => false,
            'options' => [
                ['label' => 'Opció A'],
                ['label' => 'Opció B'],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Szavazás sikeresen létrehozva!',
            ]);

        $this->assertDatabaseHas('tablo_polls', [
            'title' => 'Új Szavazás',
            'tablo_project_id' => $this->project->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->postJson('/api/tablo-frontend/polls', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'type']);
    }

    public function test_store_requires_class_size_for_first_poll(): void
    {
        // Projekt osztálylétszám nélkül
        $project = TabloProject::factory()->create([
            'expected_class_size' => null,
        ]);

        $token = $this->user->createToken('tablo-auth-token');
        $token->accessToken->tablo_project_id = $project->id;
        $token->accessToken->save();

        $this->actingAs($this->user->withAccessToken($token->accessToken), 'sanctum');

        $response = $this->postJson('/api/tablo-frontend/polls', [
            'title' => 'Új Szavazás',
            'type' => 'custom',
            'options' => [
                ['label' => 'Opció A'],
                ['label' => 'Opció B'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'requires_class_size' => true,
            ]);
    }

    public function test_store_validates_minimum_options(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->postJson('/api/tablo-frontend/polls', [
            'title' => 'Új Szavazás',
            'type' => 'custom',
            'is_free_choice' => false,
            'options' => [
                ['label' => 'Csak egy opció'],
            ],
        ]);

        $response->assertStatus(422);
    }

    // ==================== UPDATE TESZTEK ====================

    public function test_update_modifies_poll(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $poll = $this->createPollWithOptions();

        $response = $this->putJson("/api/tablo-frontend/polls/{$poll->id}", [
            'title' => 'Módosított Cím',
            'is_active' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Szavazás sikeresen frissítve!',
            ]);

        $poll->refresh();
        $this->assertEquals('Módosított Cím', $poll->title);
        $this->assertFalse($poll->is_active);
    }

    public function test_update_returns_404_for_nonexistent_poll(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->putJson('/api/tablo-frontend/polls/99999', [
            'title' => 'Módosított',
        ]);

        $response->assertStatus(404);
    }

    // ==================== DESTROY TESZTEK ====================

    public function test_destroy_deletes_poll(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $poll = $this->createPollWithOptions();

        $response = $this->deleteJson("/api/tablo-frontend/polls/{$poll->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Szavazás sikeresen törölve!',
            ]);

        $this->assertDatabaseMissing('tablo_polls', ['id' => $poll->id]);
    }

    public function test_destroy_returns_404_for_nonexistent_poll(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->deleteJson('/api/tablo-frontend/polls/99999');

        $response->assertStatus(404);
    }

    // ==================== VOTE TESZTEK ====================

    public function test_vote_casts_vote_successfully(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $poll = $this->createPollWithOptions();
        $option = $poll->options->first();

        $response = $this->withHeaders([
            'X-Guest-Session' => $this->guestSession->session_token,
        ])->postJson("/api/tablo-frontend/polls/{$poll->id}/vote", [
            'option_id' => $option->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Sikeres szavazat!',
            ]);

        $this->assertDatabaseHas('tablo_poll_votes', [
            'tablo_poll_id' => $poll->id,
            'poll_option_id' => $option->id,
            'guest_session_id' => $this->guestSession->id,
        ]);
    }

    public function test_vote_requires_guest_session(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $poll = $this->createPollWithOptions();
        $option = $poll->options->first();

        $response = $this->postJson("/api/tablo-frontend/polls/{$poll->id}/vote", [
            'option_id' => $option->id,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Hiányzó session azonosító.',
            ]);
    }

    public function test_vote_rejects_banned_guest(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $poll = $this->createPollWithOptions();
        $option = $poll->options->first();

        // Guest bannolása
        $this->guestSession->update(['is_banned' => true]);

        $response = $this->withHeaders([
            'X-Guest-Session' => $this->guestSession->session_token,
        ])->postJson("/api/tablo-frontend/polls/{$poll->id}/vote", [
            'option_id' => $option->id,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'A szavazás nem engedélyezett.',
            ]);
    }

    public function test_vote_returns_404_for_invalid_poll(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->withHeaders([
            'X-Guest-Session' => $this->guestSession->session_token,
        ])->postJson('/api/tablo-frontend/polls/99999/vote', [
            'option_id' => 1,
        ]);

        $response->assertStatus(404);
    }

    public function test_vote_validates_option_id(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $poll = $this->createPollWithOptions();

        $response = $this->withHeaders([
            'X-Guest-Session' => $this->guestSession->session_token,
        ])->postJson("/api/tablo-frontend/polls/{$poll->id}/vote", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['option_id']);
    }

    // ==================== REMOVE VOTE TESZTEK ====================

    public function test_remove_vote_removes_vote(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $poll = $this->createPollWithOptions();
        $option = $poll->options->first();

        // Szavazat leadása
        TabloPollVote::create([
            'tablo_poll_id' => $poll->id,
            'poll_option_id' => $option->id,
            'guest_session_id' => $this->guestSession->id,
            'voted_at' => now(),
        ]);

        $response = $this->withHeaders([
            'X-Guest-Session' => $this->guestSession->session_token,
        ])->deleteJson("/api/tablo-frontend/polls/{$poll->id}/vote", [
            'option_id' => $option->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Szavazat visszavonva!',
            ]);

        $this->assertDatabaseMissing('tablo_poll_votes', [
            'tablo_poll_id' => $poll->id,
            'guest_session_id' => $this->guestSession->id,
        ]);
    }

    public function test_remove_vote_requires_guest_session(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $poll = $this->createPollWithOptions();

        $response = $this->deleteJson("/api/tablo-frontend/polls/{$poll->id}/vote");

        $response->assertStatus(401);
    }

    // ==================== RESULTS TESZTEK ====================

    public function test_results_returns_poll_results(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $poll = $this->createPollWithOptions();
        $option = $poll->options->first();

        // Szavazat leadása
        TabloPollVote::create([
            'tablo_poll_id' => $poll->id,
            'poll_option_id' => $option->id,
            'guest_session_id' => $this->guestSession->id,
            'voted_at' => now(),
        ]);

        $response = $this->getJson("/api/tablo-frontend/polls/{$poll->id}/results");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_votes',
                    'unique_voters',
                    'options',
                ],
            ]);
    }

    public function test_results_returns_404_for_nonexistent_poll(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->getJson('/api/tablo-frontend/polls/99999/results');

        $response->assertStatus(404);
    }

    // ==================== AUTHENTICATION TESZTEK ====================

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/tablo-frontend/polls');

        $response->assertStatus(401);
    }
}

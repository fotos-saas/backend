<?php

namespace Tests\Feature\Api\Tablo;

use App\Models\TabloDiscussion;
use App\Models\TabloDiscussionPost;
use App\Models\TabloGuestSession;
use App\Models\TabloPostLike;
use App\Models\TabloProject;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * DiscussionController Feature Tesztek
 *
 * Fórum/beszélgetés API végpontok tesztelése.
 *
 * FONTOS: DatabaseTransactions használata RefreshDatabase HELYETT!
 */
class DiscussionControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected TabloProject $project;
    protected TabloGuestSession $guestSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = TabloProject::factory()->create();
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
     * Helper: Beszélgetés létrehozása hozzászólásokkal
     */
    protected function createDiscussionWithPosts(int $postCount = 3): TabloDiscussion
    {
        $discussion = TabloDiscussion::create([
            'tablo_project_id' => $this->project->id,
            'title' => 'Teszt Beszélgetés',
            'slug' => 'teszt-beszelgetes-' . Str::random(5),
            'creator_type' => TabloDiscussion::CREATOR_TYPE_CONTACT,
            'creator_id' => 1,
        ]);

        for ($i = 1; $i <= $postCount; $i++) {
            TabloDiscussionPost::create([
                'discussion_id' => $discussion->id,
                'author_type' => TabloDiscussionPost::AUTHOR_TYPE_GUEST,
                'author_id' => $this->guestSession->id,
                'content' => "Teszt hozzászólás $i",
            ]);
        }

        $discussion->update(['posts_count' => $postCount]);

        return $discussion->fresh();
    }

    // ==================== INDEX TESZTEK ====================

    public function test_index_returns_discussions_list(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $this->createDiscussionWithPosts();

        $response = $this->getJson('/api/tablo-frontend/discussions');

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
                        'slug',
                        'creator_name',
                        'is_pinned',
                        'is_locked',
                        'posts_count',
                        'views_count',
                    ],
                ],
            ]);
    }

    public function test_index_returns_404_for_invalid_project(): void
    {
        $token = $this->user->createToken('tablo-auth-token');
        $token->accessToken->tablo_project_id = 99999;
        $token->accessToken->save();

        $this->actingAs($this->user->withAccessToken($token->accessToken), 'sanctum');

        $response = $this->getJson('/api/tablo-frontend/discussions');

        $response->assertStatus(404);
    }

    // ==================== SHOW TESZTEK ====================

    public function test_show_returns_discussion_with_posts(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $discussion = $this->createDiscussionWithPosts();

        $response = $this->getJson("/api/tablo-frontend/discussions/{$discussion->slug}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'discussion' => [
                        'title' => 'Teszt Beszélgetés',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'discussion' => [
                        'id',
                        'title',
                        'slug',
                        'is_pinned',
                        'is_locked',
                        'can_add_posts',
                    ],
                    'posts',
                ],
            ]);
    }

    public function test_show_returns_404_for_nonexistent_discussion(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->getJson('/api/tablo-frontend/discussions/nem-letezo-slug');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Beszélgetés nem található',
            ]);
    }

    // ==================== STORE TESZTEK ====================

    public function test_store_creates_new_discussion(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->postJson('/api/tablo-frontend/discussions', [
            'title' => 'Új Beszélgetés',
            'content' => 'Ez az első hozzászólás tartalma, ami elég hosszú.',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Beszélgetés sikeresen létrehozva!',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'slug',
                ],
            ]);

        $this->assertDatabaseHas('tablo_discussions', [
            'title' => 'Új Beszélgetés',
            'tablo_project_id' => $this->project->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->postJson('/api/tablo-frontend/discussions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'content']);
    }

    public function test_store_validates_minimum_title_length(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->postJson('/api/tablo-frontend/discussions', [
            'title' => 'AB', // Túl rövid
            'content' => 'Elég hosszú tartalom a validációhoz.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_store_validates_minimum_content_length(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->postJson('/api/tablo-frontend/discussions', [
            'title' => 'Elég Hosszú Cím',
            'content' => 'Rövid', // Túl rövid
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    // ==================== UPDATE TESZTEK ====================

    public function test_update_modifies_discussion(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $discussion = $this->createDiscussionWithPosts();

        $response = $this->putJson("/api/tablo-frontend/discussions/{$discussion->id}", [
            'title' => 'Módosított Cím',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Beszélgetés sikeresen frissítve!',
            ]);

        $discussion->refresh();
        $this->assertEquals('Módosított Cím', $discussion->title);
    }

    public function test_update_returns_404_for_nonexistent_discussion(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->putJson('/api/tablo-frontend/discussions/99999', [
            'title' => 'Módosított',
        ]);

        $response->assertStatus(404);
    }

    // ==================== DESTROY TESZTEK ====================

    public function test_destroy_deletes_discussion(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $discussion = $this->createDiscussionWithPosts();

        $response = $this->deleteJson("/api/tablo-frontend/discussions/{$discussion->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Beszélgetés sikeresen törölve!',
            ]);

        $this->assertDatabaseMissing('tablo_discussions', ['id' => $discussion->id]);
    }

    public function test_destroy_returns_404_for_nonexistent_discussion(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->deleteJson('/api/tablo-frontend/discussions/99999');

        $response->assertStatus(404);
    }

    // ==================== LOCK/UNLOCK TESZTEK ====================

    public function test_lock_locks_discussion(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $discussion = $this->createDiscussionWithPosts();

        $response = $this->postJson("/api/tablo-frontend/discussions/{$discussion->id}/lock");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Beszélgetés lezárva!',
            ]);

        $discussion->refresh();
        $this->assertTrue($discussion->is_locked);
    }

    public function test_unlock_unlocks_discussion(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $discussion = $this->createDiscussionWithPosts();
        $discussion->update(['is_locked' => true]);

        $response = $this->postJson("/api/tablo-frontend/discussions/{$discussion->id}/unlock");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Beszélgetés feloldva!',
            ]);

        $discussion->refresh();
        $this->assertFalse($discussion->is_locked);
    }

    // ==================== PIN/UNPIN TESZTEK ====================

    public function test_pin_pins_discussion(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $discussion = $this->createDiscussionWithPosts();

        $response = $this->postJson("/api/tablo-frontend/discussions/{$discussion->id}/pin");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Beszélgetés kitűzve!',
            ]);

        $discussion->refresh();
        $this->assertTrue($discussion->is_pinned);
    }

    public function test_unpin_unpins_discussion(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $discussion = $this->createDiscussionWithPosts();
        $discussion->update(['is_pinned' => true]);

        $response = $this->postJson("/api/tablo-frontend/discussions/{$discussion->id}/unpin");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Beszélgetés levéve a kitűzésből!',
            ]);

        $discussion->refresh();
        $this->assertFalse($discussion->is_pinned);
    }

    // ==================== CREATE POST TESZTEK ====================

    public function test_create_post_adds_new_post(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $discussion = $this->createDiscussionWithPosts(0);

        $response = $this->withHeaders([
            'X-Guest-Session' => $this->guestSession->session_token,
        ])->postJson("/api/tablo-frontend/discussions/{$discussion->id}/posts", [
            'content' => 'Ez egy új hozzászólás',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Hozzászólás sikeresen létrehozva!',
            ]);

        $this->assertDatabaseHas('tablo_discussion_posts', [
            'discussion_id' => $discussion->id,
            'content' => 'Ez egy új hozzászólás',
        ]);
    }

    public function test_create_post_requires_guest_session(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $discussion = $this->createDiscussionWithPosts();

        $response = $this->postJson("/api/tablo-frontend/discussions/{$discussion->id}/posts", [
            'content' => 'Ez egy új hozzászólás',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Hiányzó session azonosító.',
            ]);
    }

    public function test_create_post_rejects_locked_discussion(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $discussion = $this->createDiscussionWithPosts();
        $discussion->update(['is_locked' => true]);

        $response = $this->withHeaders([
            'X-Guest-Session' => $this->guestSession->session_token,
        ])->postJson("/api/tablo-frontend/discussions/{$discussion->id}/posts", [
            'content' => 'Ez egy új hozzászólás',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'A beszélgetés le van zárva.',
            ]);
    }

    public function test_create_post_rejects_banned_guest(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $discussion = $this->createDiscussionWithPosts();

        $this->guestSession->update(['is_banned' => true]);

        $response = $this->withHeaders([
            'X-Guest-Session' => $this->guestSession->session_token,
        ])->postJson("/api/tablo-frontend/discussions/{$discussion->id}/posts", [
            'content' => 'Ez egy új hozzászólás',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'A hozzászólás nem engedélyezett.',
            ]);
    }

    public function test_create_post_supports_reply(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $discussion = $this->createDiscussionWithPosts(1);
        $parentPost = $discussion->posts->first();

        $response = $this->withHeaders([
            'X-Guest-Session' => $this->guestSession->session_token,
        ])->postJson("/api/tablo-frontend/discussions/{$discussion->id}/posts", [
            'content' => 'Ez egy válasz',
            'parent_id' => $parentPost->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('tablo_discussion_posts', [
            'discussion_id' => $discussion->id,
            'parent_id' => $parentPost->id,
            'content' => 'Ez egy válasz',
        ]);
    }

    // ==================== UPDATE POST TESZTEK ====================

    public function test_update_post_modifies_content(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $discussion = $this->createDiscussionWithPosts(1);
        $post = $discussion->posts->first();

        $response = $this->withHeaders([
            'X-Guest-Session' => $this->guestSession->session_token,
        ])->putJson("/api/tablo-frontend/posts/{$post->id}", [
            'content' => 'Módosított tartalom',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Hozzászólás sikeresen frissítve!',
            ]);

        $post->refresh();
        $this->assertEquals('Módosított tartalom', $post->content);
    }

    public function test_update_post_requires_ownership(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $discussion = $this->createDiscussionWithPosts();

        // Másik guest létrehozása és hozzászólás
        $otherGuest = TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Másik Vendég',
        ]);

        $post = TabloDiscussionPost::create([
            'discussion_id' => $discussion->id,
            'author_type' => TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            'author_id' => $otherGuest->id,
            'content' => 'Másik vendég hozzászólása',
        ]);

        // Az eredeti guest próbál szerkeszteni
        $response = $this->withHeaders([
            'X-Guest-Session' => $this->guestSession->session_token,
        ])->putJson("/api/tablo-frontend/posts/{$post->id}", [
            'content' => 'Próbálom módosítani',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Nincs jogosultságod szerkeszteni ezt a hozzászólást.',
            ]);
    }

    // ==================== DELETE POST TESZTEK ====================

    public function test_delete_post_removes_post(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $discussion = $this->createDiscussionWithPosts(1);
        $post = $discussion->posts->first();

        $response = $this->withHeaders([
            'X-Guest-Session' => $this->guestSession->session_token,
        ])->deleteJson("/api/tablo-frontend/posts/{$post->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Hozzászólás sikeresen törölve!',
            ]);
    }

    public function test_delete_post_returns_404_for_nonexistent(): void
    {
        $this->createTokenWithType('tablo-auth-token');

        $response = $this->withHeaders([
            'X-Guest-Session' => $this->guestSession->session_token,
        ])->deleteJson('/api/tablo-frontend/posts/99999');

        $response->assertStatus(404);
    }

    // ==================== TOGGLE LIKE TESZTEK ====================

    public function test_toggle_like_adds_like(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $discussion = $this->createDiscussionWithPosts(1);
        $post = $discussion->posts->first();

        $response = $this->withHeaders([
            'X-Guest-Session' => $this->guestSession->session_token,
        ])->postJson("/api/tablo-frontend/posts/{$post->id}/like");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_liked' => true,
                ],
            ]);

        $this->assertDatabaseHas('tablo_post_likes', [
            'post_id' => $post->id,
            'liker_type' => TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            'liker_id' => $this->guestSession->id,
        ]);
    }

    public function test_toggle_like_removes_like(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $discussion = $this->createDiscussionWithPosts(1);
        $post = $discussion->posts->first();

        // Like létrehozása
        TabloPostLike::create([
            'post_id' => $post->id,
            'liker_type' => TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            'liker_id' => $this->guestSession->id,
        ]);
        $post->increment('likes_count');

        $response = $this->withHeaders([
            'X-Guest-Session' => $this->guestSession->session_token,
        ])->postJson("/api/tablo-frontend/posts/{$post->id}/like");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_liked' => false,
                ],
            ]);

        $this->assertDatabaseMissing('tablo_post_likes', [
            'post_id' => $post->id,
            'liker_type' => TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            'liker_id' => $this->guestSession->id,
        ]);
    }

    public function test_toggle_like_requires_guest_session(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $discussion = $this->createDiscussionWithPosts(1);
        $post = $discussion->posts->first();

        $response = $this->postJson("/api/tablo-frontend/posts/{$post->id}/like");

        $response->assertStatus(401);
    }

    public function test_toggle_like_rejects_banned_guest(): void
    {
        $this->createTokenWithType('tablo-auth-token');
        $discussion = $this->createDiscussionWithPosts(1);
        $post = $discussion->posts->first();

        $this->guestSession->update(['is_banned' => true]);

        $response = $this->withHeaders([
            'X-Guest-Session' => $this->guestSession->session_token,
        ])->postJson("/api/tablo-frontend/posts/{$post->id}/like");

        $response->assertStatus(403);
    }

    // ==================== AUTHENTICATION TESZTEK ====================

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/tablo-frontend/discussions');

        $response->assertStatus(401);
    }
}

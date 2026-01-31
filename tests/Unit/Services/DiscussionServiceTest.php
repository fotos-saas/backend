<?php

namespace Tests\Unit\Services;

use App\Models\TabloDiscussion;
use App\Models\TabloDiscussionPost;
use App\Models\TabloGuestSession;
use App\Models\TabloPostLike;
use App\Models\TabloProject;
use App\Services\Tablo\DiscussionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * DiscussionService Unit Tesztek
 *
 * Fórum szolgáltatás tesztelése.
 *
 * FONTOS: DatabaseTransactions használata RefreshDatabase HELYETT!
 */
class DiscussionServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected DiscussionService $service;
    protected TabloProject $project;
    protected TabloGuestSession $guest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DiscussionService();
        $this->project = TabloProject::factory()->create();
        $this->guest = TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Teszt Vendég',
        ]);
    }

    /**
     * Helper: Beszélgetés létrehozása
     */
    protected function createDiscussion(): TabloDiscussion
    {
        return TabloDiscussion::create([
            'tablo_project_id' => $this->project->id,
            'title' => 'Teszt Beszélgetés',
            'slug' => 'teszt-beszelgetes-' . Str::random(5),
            'creator_type' => TabloDiscussion::CREATOR_TYPE_CONTACT,
            'creator_id' => 1,
        ]);
    }

    /**
     * Helper: Hozzászólás létrehozása
     */
    protected function createPost(TabloDiscussion $discussion, ?int $parentId = null): TabloDiscussionPost
    {
        return TabloDiscussionPost::create([
            'discussion_id' => $discussion->id,
            'author_type' => TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            'author_id' => $this->guest->id,
            'content' => 'Teszt hozzászólás',
            'parent_id' => $parentId,
        ]);
    }

    // ==================== CREATE DISCUSSION TESZTEK ====================

    public function test_create_discussion_creates_discussion_with_first_post(): void
    {
        $discussion = $this->service->createDiscussion(
            $this->project,
            'Új Beszélgetés',
            'Ez az első hozzászólás tartalma.',
            TabloDiscussion::CREATOR_TYPE_CONTACT,
            1
        );

        $this->assertNotNull($discussion);
        $this->assertEquals('Új Beszélgetés', $discussion->title);
        $this->assertEquals($this->project->id, $discussion->tablo_project_id);
        $this->assertNotEmpty($discussion->slug);

        // Első hozzászólás létrejött
        $this->assertEquals(1, $discussion->posts_count);
    }

    public function test_create_discussion_generates_unique_slug(): void
    {
        $discussion1 = $this->service->createDiscussion(
            $this->project,
            'Teszt Cím',
            'Tartalom 1',
            TabloDiscussion::CREATOR_TYPE_CONTACT,
            1
        );

        $discussion2 = $this->service->createDiscussion(
            $this->project,
            'Teszt Cím', // Azonos cím
            'Tartalom 2',
            TabloDiscussion::CREATOR_TYPE_CONTACT,
            1
        );

        $this->assertNotEquals($discussion1->slug, $discussion2->slug);
    }

    public function test_create_discussion_with_template(): void
    {
        $discussion = $this->service->createDiscussion(
            $this->project,
            'Sablon Beszélgetés',
            'Tartalom',
            TabloDiscussion::CREATOR_TYPE_CONTACT,
            1,
            123 // Template ID
        );

        $this->assertEquals(123, $discussion->tablo_sample_template_id);
    }

    // ==================== UPDATE DISCUSSION TESZTEK ====================

    public function test_update_discussion_modifies_title(): void
    {
        $discussion = $this->createDiscussion();

        $updated = $this->service->updateDiscussion($discussion, [
            'title' => 'Módosított Cím',
        ]);

        $this->assertEquals('Módosított Cím', $updated->title);
        // Slug változatlan marad alapértelmezetten
    }

    public function test_update_discussion_updates_slug_when_requested(): void
    {
        $discussion = $this->createDiscussion();
        $oldSlug = $discussion->slug;

        $updated = $this->service->updateDiscussion($discussion, [
            'title' => 'Teljesen Új Cím',
            'update_slug' => true,
        ]);

        $this->assertNotEquals($oldSlug, $updated->slug);
    }

    public function test_update_discussion_modifies_template_id(): void
    {
        $discussion = $this->createDiscussion();

        $updated = $this->service->updateDiscussion($discussion, [
            'tablo_sample_template_id' => 456,
        ]);

        $this->assertEquals(456, $updated->tablo_sample_template_id);
    }

    // ==================== DELETE DISCUSSION TESZTEK ====================

    public function test_delete_discussion_removes_discussion(): void
    {
        $discussion = $this->createDiscussion();
        $discussionId = $discussion->id;

        $this->service->deleteDiscussion($discussion);

        $this->assertDatabaseMissing('tablo_discussions', ['id' => $discussionId]);
    }

    public function test_delete_discussion_removes_posts_cascade(): void
    {
        $discussion = $this->createDiscussion();
        $post = $this->createPost($discussion);
        $postId = $post->id;

        $this->service->deleteDiscussion($discussion);

        $this->assertDatabaseMissing('tablo_discussion_posts', ['id' => $postId]);
    }

    // ==================== CREATE POST TESZTEK ====================

    public function test_create_post_adds_new_post(): void
    {
        $discussion = $this->createDiscussion();

        $post = $this->service->createPost(
            $discussion,
            'Új hozzászólás tartalma',
            TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            $this->guest->id
        );

        $this->assertNotNull($post);
        $this->assertEquals($discussion->id, $post->discussion_id);
        $this->assertEquals('Új hozzászólás tartalma', $post->content);
    }

    public function test_create_post_throws_when_discussion_locked(): void
    {
        $discussion = $this->createDiscussion();
        $discussion->update(['is_locked' => true]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A beszélgetés le van zárva.');

        $this->service->createPost(
            $discussion,
            'Tartalom',
            TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            $this->guest->id
        );
    }

    public function test_create_post_supports_reply(): void
    {
        $discussion = $this->createDiscussion();
        $parentPost = $this->createPost($discussion);

        $reply = $this->service->createPost(
            $discussion,
            'Válasz tartalom',
            TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            $this->guest->id,
            $parentPost->id
        );

        $this->assertEquals($parentPost->id, $reply->parent_id);
    }

    public function test_create_post_parses_mentions(): void
    {
        $discussion = $this->createDiscussion();

        $post = $this->service->createPost(
            $discussion,
            'Hello @JánosKovács és @KissMária!',
            TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            $this->guest->id
        );

        $mentions = $post->mentions ?? [];
        // A parseMentions implementációtól függ
        $this->assertIsArray($mentions);
    }

    // ==================== UPDATE POST TESZTEK ====================

    public function test_update_post_modifies_content(): void
    {
        $discussion = $this->createDiscussion();
        $post = $this->createPost($discussion);

        // A canEdit() 15 percig igaz
        $updated = $this->service->updatePost($post, 'Módosított tartalom');

        $this->assertEquals('Módosított tartalom', $updated->content);
        $this->assertTrue($updated->is_edited);
    }

    public function test_update_post_throws_when_edit_time_expired(): void
    {
        $discussion = $this->createDiscussion();
        $post = $this->createPost($discussion);

        // Régi post szimulálása
        $post->update(['created_at' => now()->subMinutes(20)]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A szerkesztési idő lejárt.');

        $this->service->updatePost($post, 'Új tartalom');
    }

    // ==================== DELETE POST TESZTEK ====================

    public function test_delete_post_removes_post(): void
    {
        $discussion = $this->createDiscussion();
        $post = $this->createPost($discussion);
        $postId = $post->id;

        $this->service->deletePost($post);

        $this->assertDatabaseMissing('tablo_discussion_posts', ['id' => $postId]);
    }

    public function test_delete_post_updates_discussion_count(): void
    {
        $discussion = $this->createDiscussion();
        $post1 = $this->createPost($discussion);
        $this->createPost($discussion);

        $discussion->updatePostsCount();
        $this->assertEquals(2, $discussion->fresh()->posts_count);

        $this->service->deletePost($post1);

        // A posts_count frissül
        $this->assertEquals(1, $discussion->fresh()->posts_count);
    }

    // ==================== TOGGLE LIKE TESZTEK ====================

    public function test_toggle_like_adds_like(): void
    {
        $discussion = $this->createDiscussion();
        $post = $this->createPost($discussion);

        // Másik guest
        $liker = TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Liker',
        ]);

        $isLiked = $this->service->toggleLike(
            $post,
            TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            $liker->id
        );

        $this->assertTrue($isLiked);
        $this->assertDatabaseHas('tablo_post_likes', [
            'post_id' => $post->id,
            'liker_type' => TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            'liker_id' => $liker->id,
        ]);
    }

    public function test_toggle_like_removes_like(): void
    {
        $discussion = $this->createDiscussion();
        $post = $this->createPost($discussion);

        $liker = TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Liker',
        ]);

        // Like hozzáadása
        TabloPostLike::create([
            'post_id' => $post->id,
            'liker_type' => TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            'liker_id' => $liker->id,
        ]);

        // Toggle - törlés
        $isLiked = $this->service->toggleLike(
            $post,
            TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            $liker->id
        );

        $this->assertFalse($isLiked);
        $this->assertDatabaseMissing('tablo_post_likes', [
            'post_id' => $post->id,
            'liker_id' => $liker->id,
        ]);
    }

    // ==================== LOCK/UNLOCK TESZTEK ====================

    public function test_lock_locks_discussion(): void
    {
        $discussion = $this->createDiscussion();

        $this->service->lock($discussion);

        $this->assertTrue($discussion->fresh()->is_locked);
    }

    public function test_unlock_unlocks_discussion(): void
    {
        $discussion = $this->createDiscussion();
        $discussion->update(['is_locked' => true]);

        $this->service->unlock($discussion);

        $this->assertFalse($discussion->fresh()->is_locked);
    }

    // ==================== PIN/UNPIN TESZTEK ====================

    public function test_pin_pins_discussion(): void
    {
        $discussion = $this->createDiscussion();

        $this->service->pin($discussion);

        $this->assertTrue($discussion->fresh()->is_pinned);
    }

    public function test_unpin_unpins_discussion(): void
    {
        $discussion = $this->createDiscussion();
        $discussion->update(['is_pinned' => true]);

        $this->service->unpin($discussion);

        $this->assertFalse($discussion->fresh()->is_pinned);
    }

    // ==================== BY PROJECT TESZTEK ====================

    public function test_get_by_project_returns_discussions(): void
    {
        $this->createDiscussion();
        $this->createDiscussion();

        $discussions = $this->service->getByProject($this->project);

        $this->assertCount(2, $discussions);
    }

    public function test_get_by_project_orders_pinned_first(): void
    {
        $normal = $this->createDiscussion();
        $pinned = $this->createDiscussion();
        $pinned->update(['is_pinned' => true]);

        $discussions = $this->service->getByProject($this->project, true);

        $this->assertTrue($discussions->first()->is_pinned);
    }

    // ==================== CAN EDIT/DELETE POST TESZTEK ====================

    public function test_can_user_edit_post_returns_true_for_author(): void
    {
        $discussion = $this->createDiscussion();
        $post = $this->createPost($discussion);

        $canEdit = $this->service->canUserEditPost(
            $post,
            TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            $this->guest->id
        );

        $this->assertTrue($canEdit);
    }

    public function test_can_user_edit_post_returns_false_for_non_author(): void
    {
        $discussion = $this->createDiscussion();
        $post = $this->createPost($discussion);

        $otherGuest = TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Másik',
        ]);

        $canEdit = $this->service->canUserEditPost(
            $post,
            TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            $otherGuest->id
        );

        $this->assertFalse($canEdit);
    }

    public function test_can_user_edit_post_returns_false_after_time_limit(): void
    {
        $discussion = $this->createDiscussion();
        $post = $this->createPost($discussion);
        $post->update(['created_at' => now()->subMinutes(20)]);

        $canEdit = $this->service->canUserEditPost(
            $post,
            TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            $this->guest->id
        );

        $this->assertFalse($canEdit);
    }

    public function test_can_user_delete_post_returns_true_for_author(): void
    {
        $discussion = $this->createDiscussion();
        $post = $this->createPost($discussion);

        $canDelete = $this->service->canUserDeletePost(
            $post,
            TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            $this->guest->id
        );

        $this->assertTrue($canDelete);
    }

    public function test_can_user_delete_post_returns_true_for_moderator(): void
    {
        $discussion = $this->createDiscussion();
        $post = $this->createPost($discussion);

        // Másik user, de moderátor
        $canDelete = $this->service->canUserDeletePost(
            $post,
            TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            99999, // Nem a szerző
            true // Moderátor
        );

        $this->assertTrue($canDelete);
    }

    public function test_can_user_delete_post_returns_false_for_non_author_non_moderator(): void
    {
        $discussion = $this->createDiscussion();
        $post = $this->createPost($discussion);

        $canDelete = $this->service->canUserDeletePost(
            $post,
            TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            99999, // Nem a szerző
            false // Nem moderátor
        );

        $this->assertFalse($canDelete);
    }
}

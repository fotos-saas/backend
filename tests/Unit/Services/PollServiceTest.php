<?php

namespace Tests\Unit\Services;

use App\Models\TabloGuestSession;
use App\Models\TabloPoll;
use App\Models\TabloPollOption;
use App\Models\TabloPollVote;
use App\Models\TabloProject;
use App\Services\Tablo\PollService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PollService Unit Tesztek
 *
 * Szavazás szolgáltatás tesztelése.
 *
 * FONTOS: DatabaseTransactions használata RefreshDatabase HELYETT!
 */
class PollServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected PollService $service;
    protected TabloProject $project;
    protected TabloGuestSession $guest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PollService();
        $this->project = TabloProject::factory()->create([
            'expected_class_size' => 30,
        ]);
        $this->guest = TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Teszt Vendég',
        ]);
    }

    /**
     * Helper: Szavazás létrehozása opcióval
     */
    protected function createPollWithOptions(int $optionCount = 3, bool $isActive = true): TabloPoll
    {
        $poll = TabloPoll::create([
            'tablo_project_id' => $this->project->id,
            'title' => 'Teszt Szavazás',
            'type' => TabloPoll::TYPE_CUSTOM,
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

    // ==================== CREATE TESZTEK ====================

    public function test_create_creates_poll_with_options(): void
    {
        $data = [
            'title' => 'Új Szavazás',
            'description' => 'Leírás',
            'type' => TabloPoll::TYPE_CUSTOM,
            'options' => [
                ['label' => 'Opció A'],
                ['label' => 'Opció B'],
                ['label' => 'Opció C'],
            ],
        ];

        $poll = $this->service->create($this->project, $data);

        $this->assertNotNull($poll);
        $this->assertEquals('Új Szavazás', $poll->title);
        $this->assertEquals($this->project->id, $poll->tablo_project_id);
        $this->assertCount(3, $poll->options);
    }

    public function test_create_sets_default_values(): void
    {
        $data = [
            'title' => 'Minimális Szavazás',
            'type' => TabloPoll::TYPE_CUSTOM,
            'options' => [
                ['label' => 'Opció A'],
                ['label' => 'Opció B'],
            ],
        ];

        $poll = $this->service->create($this->project, $data);

        $this->assertTrue($poll->is_active);
        $this->assertFalse($poll->is_multiple_choice);
        $this->assertEquals(1, $poll->max_votes_per_guest);
        $this->assertFalse($poll->show_results_before_vote);
        $this->assertFalse($poll->use_for_finalization);
    }

    public function test_create_with_custom_settings(): void
    {
        $data = [
            'title' => 'Komplex Szavazás',
            'type' => TabloPoll::TYPE_CUSTOM,
            'is_multiple_choice' => true,
            'max_votes_per_guest' => 3,
            'show_results_before_vote' => true,
            'use_for_finalization' => true,
            'close_at' => now()->addDays(7)->toDateTimeString(),
            'options' => [
                ['label' => 'A'],
                ['label' => 'B'],
            ],
        ];

        $poll = $this->service->create($this->project, $data);

        $this->assertTrue($poll->is_multiple_choice);
        $this->assertEquals(3, $poll->max_votes_per_guest);
        $this->assertTrue($poll->show_results_before_vote);
        $this->assertTrue($poll->use_for_finalization);
        $this->assertNotNull($poll->close_at);
    }

    // ==================== UPDATE TESZTEK ====================

    public function test_update_modifies_poll(): void
    {
        $poll = $this->createPollWithOptions();

        $updated = $this->service->update($poll, [
            'title' => 'Módosított Cím',
            'is_active' => false,
        ]);

        $this->assertEquals('Módosított Cím', $updated->title);
        $this->assertFalse($updated->is_active);
    }

    public function test_update_replaces_options(): void
    {
        $poll = $this->createPollWithOptions(3);
        $originalOptionIds = $poll->options->pluck('id')->toArray();

        $this->service->update($poll, [
            'options' => [
                ['label' => 'Új A'],
                ['label' => 'Új B'],
            ],
        ]);

        $poll->refresh();

        // Régi opciók törölve
        foreach ($originalOptionIds as $id) {
            $this->assertDatabaseMissing('tablo_poll_options', ['id' => $id]);
        }

        // Új opciók létrejöttek
        $this->assertCount(2, $poll->options);
        $this->assertEquals('Új A', $poll->options->first()->label);
    }

    // ==================== DELETE TESZTEK ====================

    public function test_delete_removes_poll(): void
    {
        $poll = $this->createPollWithOptions();
        $pollId = $poll->id;

        $this->service->delete($poll);

        $this->assertDatabaseMissing('tablo_polls', ['id' => $pollId]);
    }

    public function test_delete_removes_options_cascade(): void
    {
        $poll = $this->createPollWithOptions(3);
        $optionIds = $poll->options->pluck('id')->toArray();

        $this->service->delete($poll);

        foreach ($optionIds as $id) {
            $this->assertDatabaseMissing('tablo_poll_options', ['id' => $id]);
        }
    }

    // ==================== VOTE TESZTEK ====================

    public function test_vote_creates_vote_record(): void
    {
        $poll = $this->createPollWithOptions();
        $option = $poll->options->first();

        $vote = $this->service->vote($poll, $this->guest, $option->id);

        $this->assertNotNull($vote);
        $this->assertEquals($poll->id, $vote->tablo_poll_id);
        $this->assertEquals($option->id, $vote->poll_option_id);
        $this->assertEquals($this->guest->id, $vote->guest_session_id);
    }

    public function test_vote_throws_when_poll_closed(): void
    {
        $poll = $this->createPollWithOptions(3, false);
        $option = $poll->options->first();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A szavazás már lezárult.');

        $this->service->vote($poll, $this->guest, $option->id);
    }

    public function test_vote_throws_when_guest_banned(): void
    {
        $poll = $this->createPollWithOptions();
        $option = $poll->options->first();

        $this->guest->update(['is_banned' => true]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A szavazás nem engedélyezett.');

        $this->service->vote($poll, $this->guest, $option->id);
    }

    public function test_vote_throws_when_max_votes_reached(): void
    {
        $poll = $this->createPollWithOptions(3);
        $poll->update(['max_votes_per_guest' => 1]);

        // Első szavazat
        $this->service->vote($poll, $this->guest, $poll->options->first()->id);

        // Második szavazat - túl sok
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Elérted a maximális szavazatszámot.');

        $this->service->vote($poll, $this->guest, $poll->options->get(1)->id);
    }

    public function test_vote_throws_when_option_invalid(): void
    {
        $poll = $this->createPollWithOptions();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Érvénytelen opció.');

        $this->service->vote($poll, $this->guest, 99999);
    }

    public function test_vote_throws_when_already_voted_same_option(): void
    {
        $poll = $this->createPollWithOptions();
        $poll->update(['is_multiple_choice' => true, 'max_votes_per_guest' => 5]);
        $option = $poll->options->first();

        // Első szavazat
        $this->service->vote($poll, $this->guest, $option->id);

        // Ugyanarra az opcióra újra
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Már szavaztál erre az opcióra.');

        $this->service->vote($poll, $this->guest, $option->id);
    }

    // ==================== REMOVE VOTE TESZTEK ====================

    public function test_remove_vote_removes_specific_vote(): void
    {
        $poll = $this->createPollWithOptions();
        $poll->update(['is_multiple_choice' => true, 'max_votes_per_guest' => 3]);

        // Több szavazat leadása
        $option1 = $poll->options->first();
        $option2 = $poll->options->get(1);

        $this->service->vote($poll, $this->guest, $option1->id);
        $this->service->vote($poll, $this->guest, $option2->id);

        // Egy szavazat törlése
        $deleted = $this->service->removeVote($poll, $this->guest, $option1->id);

        $this->assertEquals(1, $deleted);
        $this->assertDatabaseMissing('tablo_poll_votes', [
            'tablo_poll_id' => $poll->id,
            'poll_option_id' => $option1->id,
        ]);
        $this->assertDatabaseHas('tablo_poll_votes', [
            'tablo_poll_id' => $poll->id,
            'poll_option_id' => $option2->id,
        ]);
    }

    public function test_remove_vote_removes_all_guest_votes(): void
    {
        $poll = $this->createPollWithOptions();
        $poll->update(['is_multiple_choice' => true, 'max_votes_per_guest' => 3]);

        // Több szavazat
        $this->service->vote($poll, $this->guest, $poll->options->first()->id);
        $this->service->vote($poll, $this->guest, $poll->options->get(1)->id);

        // Összes törlése
        $deleted = $this->service->removeVote($poll, $this->guest, null);

        $this->assertEquals(2, $deleted);
        $this->assertDatabaseMissing('tablo_poll_votes', [
            'tablo_poll_id' => $poll->id,
            'guest_session_id' => $this->guest->id,
        ]);
    }

    public function test_remove_vote_throws_when_poll_closed(): void
    {
        $poll = $this->createPollWithOptions();
        $option = $poll->options->first();

        // Szavazat leadása
        $this->service->vote($poll, $this->guest, $option->id);

        // Lezárás
        $poll->update(['is_active' => false]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A szavazás már lezárult.');

        $this->service->removeVote($poll, $this->guest, $option->id);
    }

    // ==================== RESULTS TESZTEK ====================

    public function test_get_results_returns_statistics(): void
    {
        $poll = $this->createPollWithOptions(3);

        // Szavazatok létrehozása
        $option1 = $poll->options->first();
        $option2 = $poll->options->get(1);

        // 3 szavazat az első opcióra
        for ($i = 1; $i <= 3; $i++) {
            $guest = TabloGuestSession::create([
                'tablo_project_id' => $this->project->id,
                'session_token' => Str::uuid()->toString(),
                'guest_name' => "Vendég $i",
            ]);
            TabloPollVote::create([
                'tablo_poll_id' => $poll->id,
                'poll_option_id' => $option1->id,
                'guest_session_id' => $guest->id,
                'voted_at' => now(),
            ]);
        }

        // 2 szavazat a második opcióra
        for ($i = 4; $i <= 5; $i++) {
            $guest = TabloGuestSession::create([
                'tablo_project_id' => $this->project->id,
                'session_token' => Str::uuid()->toString(),
                'guest_name' => "Vendég $i",
            ]);
            TabloPollVote::create([
                'tablo_poll_id' => $poll->id,
                'poll_option_id' => $option2->id,
                'guest_session_id' => $guest->id,
                'voted_at' => now(),
            ]);
        }

        $results = $this->service->getResults($poll);

        $this->assertEquals($poll->id, $results['poll_id']);
        $this->assertEquals(5, $results['total_votes']);
        $this->assertCount(3, $results['options']);

        // Első opció 60%
        $this->assertEquals(3, $results['options'][0]['votes_count']);
        $this->assertEquals(60.0, $results['options'][0]['percentage']);

        // Második opció 40%
        $this->assertEquals(2, $results['options'][1]['votes_count']);
        $this->assertEquals(40.0, $results['options'][1]['percentage']);
    }

    public function test_get_results_handles_empty_poll(): void
    {
        $poll = $this->createPollWithOptions(3);

        $results = $this->service->getResults($poll);

        $this->assertEquals(0, $results['total_votes']);
        foreach ($results['options'] as $option) {
            $this->assertEquals(0, $option['votes_count']);
            $this->assertEquals(0, $option['percentage']);
        }
    }

    // ==================== GUEST VOTES TESZTEK ====================

    public function test_get_guest_votes_returns_option_ids(): void
    {
        $poll = $this->createPollWithOptions(3);
        $poll->update(['is_multiple_choice' => true, 'max_votes_per_guest' => 3]);

        $option1 = $poll->options->first();
        $option2 = $poll->options->get(1);

        $this->service->vote($poll, $this->guest, $option1->id);
        $this->service->vote($poll, $this->guest, $option2->id);

        $votes = $this->service->getGuestVotes($poll, $this->guest);

        $this->assertCount(2, $votes);
        $this->assertContains($option1->id, $votes);
        $this->assertContains($option2->id, $votes);
    }

    public function test_get_guest_votes_returns_empty_when_no_votes(): void
    {
        $poll = $this->createPollWithOptions();

        $votes = $this->service->getGuestVotes($poll, $this->guest);

        $this->assertEmpty($votes);
    }

    // ==================== CLOSE/REOPEN TESZTEK ====================

    public function test_close_deactivates_poll(): void
    {
        $poll = $this->createPollWithOptions();

        $closed = $this->service->close($poll);

        $this->assertFalse($closed->is_active);
        $this->assertNotNull($closed->close_at);
    }

    public function test_reopen_activates_poll(): void
    {
        $poll = $this->createPollWithOptions(3, false);

        $reopened = $this->service->reopen($poll);

        $this->assertTrue($reopened->is_active);
    }

    public function test_reopen_with_new_close_date(): void
    {
        $poll = $this->createPollWithOptions(3, false);
        $newCloseDate = new \DateTime('+7 days');

        $reopened = $this->service->reopen($poll, $newCloseDate);

        $this->assertTrue($reopened->is_active);
        $this->assertNotNull($reopened->close_at);
    }

    // ==================== BY PROJECT TESZTEK ====================

    public function test_get_by_project_returns_all_polls(): void
    {
        $this->createPollWithOptions(2, true);
        $this->createPollWithOptions(2, false);

        $polls = $this->service->getByProject($this->project, false);

        $this->assertCount(2, $polls);
    }

    public function test_get_by_project_filters_active_only(): void
    {
        $this->createPollWithOptions(2, true);
        $this->createPollWithOptions(2, false);

        $polls = $this->service->getByProject($this->project, true);

        $this->assertCount(1, $polls);
        $this->assertTrue($polls->first()->is_active);
    }

    // ==================== WINNERS TESZTEK ====================

    public function test_get_winners_returns_highest_voted_options(): void
    {
        $poll = $this->createPollWithOptions(3);
        $option1 = $poll->options->first();
        $option2 = $poll->options->get(1);

        // 3 szavazat az első opcióra
        for ($i = 1; $i <= 3; $i++) {
            $guest = TabloGuestSession::create([
                'tablo_project_id' => $this->project->id,
                'session_token' => Str::uuid()->toString(),
                'guest_name' => "Vendég $i",
            ]);
            TabloPollVote::create([
                'tablo_poll_id' => $poll->id,
                'poll_option_id' => $option1->id,
                'guest_session_id' => $guest->id,
                'voted_at' => now(),
            ]);
        }

        // 1 szavazat a másodikra
        TabloPollVote::create([
            'tablo_poll_id' => $poll->id,
            'poll_option_id' => $option2->id,
            'guest_session_id' => $this->guest->id,
            'voted_at' => now(),
        ]);

        $winners = $this->service->getWinners($poll);

        $this->assertCount(1, $winners);
        $this->assertEquals($option1->id, $winners->first()->id);
    }

    public function test_get_winners_returns_empty_when_no_votes(): void
    {
        $poll = $this->createPollWithOptions();

        $winners = $this->service->getWinners($poll);

        $this->assertTrue($winners->isEmpty());
    }

    public function test_get_winners_handles_tie(): void
    {
        $poll = $this->createPollWithOptions(2);
        $option1 = $poll->options->first();
        $option2 = $poll->options->get(1);

        // Egyenlő szavazatok
        $guest1 = TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Vendég 1',
        ]);
        TabloPollVote::create([
            'tablo_poll_id' => $poll->id,
            'poll_option_id' => $option1->id,
            'guest_session_id' => $guest1->id,
            'voted_at' => now(),
        ]);

        $guest2 = TabloGuestSession::create([
            'tablo_project_id' => $this->project->id,
            'session_token' => Str::uuid()->toString(),
            'guest_name' => 'Vendég 2',
        ]);
        TabloPollVote::create([
            'tablo_poll_id' => $poll->id,
            'poll_option_id' => $option2->id,
            'guest_session_id' => $guest2->id,
            'voted_at' => now(),
        ]);

        $winners = $this->service->getWinners($poll);

        // Mindkét opció győztes döntetlen esetén
        $this->assertCount(2, $winners);
    }
}

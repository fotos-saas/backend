<?php

namespace App\Services\Tablo;

use App\Models\TabloGuestSession;
use App\Models\TabloPoll;
use App\Models\TabloPollMedia;
use App\Models\TabloPollOption;
use App\Models\TabloPollVote;
use App\Models\TabloProject;
use App\Models\TabloSampleTemplate;
use App\Services\FileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Poll Service
 *
 * Szavazás kezelés:
 * - Poll CRUD
 * - Szavazat leadás
 * - Eredmények számítás
 * - Statisztikák
 */
class PollService
{
    private const MEDIA_DIRECTORY = 'polls';

    public function __construct(
        protected FileStorageService $fileStorage
    ) {}
    /**
     * Új szavazás létrehozása
     */
    public function create(TabloProject $project, array $data, ?int $creatorContactId = null, ?UploadedFile $coverImage = null): TabloPoll
    {
        return DB::transaction(function () use ($project, $data, $creatorContactId, $coverImage) {
            $poll = TabloPoll::create([
                'tablo_project_id' => $project->id,
                'creator_contact_id' => $creatorContactId,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'cover_image_url' => null,
                'type' => $data['type'] ?? TabloPoll::TYPE_CUSTOM,
                'is_free_choice' => $data['is_free_choice'] ?? false,
                'is_active' => $data['is_active'] ?? true,
                'is_multiple_choice' => $data['is_multiple_choice'] ?? false,
                'max_votes_per_guest' => $data['max_votes_per_guest'] ?? 1,
                'show_results_before_vote' => $data['show_results_before_vote'] ?? false,
                'use_for_finalization' => $data['use_for_finalization'] ?? false,
                'close_at' => $data['close_at'] ?? null,
            ]);

            // Borítókép mentése
            if ($coverImage) {
                $coverImageUrl = $this->storeCoverImage($poll, $coverImage);
                $poll->update(['cover_image_url' => $coverImageUrl]);
            }

            // Opciók hozzáadása
            if (! empty($data['options'])) {
                $this->addOptions($poll, $data['options']);
            }

            // Ha sablon típusú és free choice, hozzáadjuk az összes aktív sablont
            if ($poll->type === TabloPoll::TYPE_TEMPLATE && $poll->is_free_choice) {
                $this->addAllActiveTemplatesAsOptions($poll);
            }

            Log::info('Poll created', [
                'project_id' => $project->id,
                'poll_id' => $poll->id,
                'type' => $poll->type,
                'has_cover_image' => $coverImage !== null,
            ]);

            return $poll;
        });
    }

    /**
     * Szavazás frissítése
     */
    public function update(TabloPoll $poll, array $data): TabloPoll
    {
        $poll->update([
            'title' => $data['title'] ?? $poll->title,
            'description' => $data['description'] ?? $poll->description,
            'is_active' => $data['is_active'] ?? $poll->is_active,
            'is_multiple_choice' => $data['is_multiple_choice'] ?? $poll->is_multiple_choice,
            'max_votes_per_guest' => $data['max_votes_per_guest'] ?? $poll->max_votes_per_guest,
            'show_results_before_vote' => $data['show_results_before_vote'] ?? $poll->show_results_before_vote,
            'use_for_finalization' => $data['use_for_finalization'] ?? $poll->use_for_finalization,
            'close_at' => $data['close_at'] ?? $poll->close_at,
        ]);

        // Opciók frissítése ha van
        if (isset($data['options'])) {
            $poll->options()->delete();
            $this->addOptions($poll, $data['options']);
        }

        Log::info('Poll updated', [
            'poll_id' => $poll->id,
        ]);

        return $poll->fresh();
    }

    /**
     * Szavazás törlése
     */
    public function delete(TabloPoll $poll): void
    {
        $pollId = $poll->id;
        $projectId = $poll->tablo_project_id;

        // Borítókép törlése
        $this->deleteCoverImage($poll);

        $poll->delete();

        Log::info('Poll deleted', [
            'project_id' => $projectId,
            'poll_id' => $pollId,
        ]);
    }

    /**
     * Borítókép mentése
     */
    public function storeCoverImage(TabloPoll $poll, UploadedFile $file): string
    {
        // Régi kép törlése ha van
        $this->deleteCoverImage($poll);

        $directory = "polls/{$poll->id}";
        $result = $this->fileStorage->store($file, $directory);

        return '/storage/' . $result->path;
    }

    /**
     * Borítókép törlése
     */
    public function deleteCoverImage(TabloPoll $poll): void
    {
        if ($poll->cover_image_url) {
            $this->fileStorage->delete($poll->cover_image_url);
        }

        // Mappa törlése ha üres
        $this->fileStorage->cleanupEmptyDirectory("polls/{$poll->id}");
    }

    // ==================== MÉDIA KEZELÉS ====================

    /**
     * Média fájl feltöltése szavazáshoz
     */
    public function uploadMedia(TabloPoll $poll, UploadedFile $file, int $sortOrder = 0): TabloPollMedia
    {
        $directory = self::MEDIA_DIRECTORY . '/' . $poll->id . '/media';
        $result = $this->fileStorage->validateAndStore($file, $directory);

        return TabloPollMedia::create([
            'tablo_poll_id' => $poll->id,
            'file_path' => $result->path,
            'file_name' => $result->originalName,
            'mime_type' => $result->mimeType,
            'file_size' => $result->size,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * Több média fájl feltöltése
     *
     * @param  UploadedFile[]  $files
     * @return TabloPollMedia[]
     */
    public function uploadMediaFiles(TabloPoll $poll, array $files): array
    {
        $mediaItems = [];
        $startOrder = $poll->media()->max('sort_order') ?? -1;

        foreach ($files as $index => $file) {
            $mediaItems[] = $this->uploadMedia($poll, $file, $startOrder + $index + 1);
        }

        return $mediaItems;
    }

    /**
     * Média törlése
     */
    public function deleteMedia(TabloPollMedia $media): void
    {
        $pollId = $media->tablo_poll_id;
        $media->delete(); // Ez automatikusan törli a fájlt is a booted() hook miatt

        // Mappa törlése ha üres
        $this->fileStorage->cleanupEmptyDirectory("polls/{$pollId}/media");
    }

    /**
     * Média törlése ID alapján
     */
    public function deleteMediaById(TabloPoll $poll, int $mediaId): bool
    {
        $media = $poll->media()->find($mediaId);
        if (! $media) {
            return false;
        }

        $this->deleteMedia($media);

        return true;
    }

    /**
     * Több média törlése ID-k alapján
     *
     * @param  int[]  $mediaIds
     */
    public function deleteMediaByIds(TabloPoll $poll, array $mediaIds): int
    {
        $deleted = 0;
        foreach ($mediaIds as $mediaId) {
            if ($this->deleteMediaById($poll, $mediaId)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Opciók hozzáadása
     */
    private function addOptions(TabloPoll $poll, array $options): void
    {
        foreach ($options as $index => $option) {
            TabloPollOption::create([
                'tablo_poll_id' => $poll->id,
                'tablo_sample_template_id' => $option['template_id'] ?? null,
                'label' => $option['label'],
                'description' => $option['description'] ?? null,
                'image_url' => $option['image_url'] ?? null,
                'display_order' => $option['display_order'] ?? $index,
            ]);
        }
    }

    /**
     * Összes aktív sablon hozzáadása opcióként
     */
    private function addAllActiveTemplatesAsOptions(TabloPoll $poll): void
    {
        $templates = TabloSampleTemplate::active()->ordered()->get();

        foreach ($templates as $index => $template) {
            TabloPollOption::create([
                'tablo_poll_id' => $poll->id,
                'tablo_sample_template_id' => $template->id,
                'label' => $template->name,
                'description' => $template->description,
                'image_url' => $template->thumbnail_url,
                'display_order' => $index,
            ]);
        }
    }

    /**
     * Szavazat leadás
     */
    public function vote(TabloPoll $poll, TabloGuestSession $guest, int $optionId): TabloPollVote
    {
        // Ellenőrzések
        if (! $poll->isOpen()) {
            throw new \InvalidArgumentException('A szavazás már lezárult.');
        }

        if ($guest->is_banned) {
            throw new \InvalidArgumentException('A szavazás nem engedélyezett.');
        }

        if (! $poll->canGuestVote($guest->id)) {
            throw new \InvalidArgumentException('Elérted a maximális szavazatszámot.');
        }

        // Ellenőrizzük, hogy az opció létezik-e
        $option = $poll->options()->find($optionId);
        if (! $option) {
            throw new \InvalidArgumentException('Érvénytelen opció.');
        }

        // Ellenőrizzük, hogy erre az opcióra még nem szavazott
        $existingVote = TabloPollVote::where('tablo_poll_id', $poll->id)
            ->where('tablo_guest_session_id', $guest->id)
            ->where('tablo_poll_option_id', $optionId)
            ->exists();

        if ($existingVote) {
            throw new \InvalidArgumentException('Már szavaztál erre az opcióra.');
        }

        $vote = TabloPollVote::create([
            'tablo_poll_id' => $poll->id,
            'tablo_poll_option_id' => $optionId,
            'tablo_guest_session_id' => $guest->id,
            'voted_at' => now(),
        ]);

        Log::info('Vote cast', [
            'poll_id' => $poll->id,
            'option_id' => $optionId,
            'guest_id' => $guest->id,
        ]);

        return $vote;
    }

    /**
     * Szavazat visszavonás
     */
    public function removeVote(TabloPoll $poll, TabloGuestSession $guest, ?int $optionId = null): int
    {
        if (! $poll->isOpen()) {
            throw new \InvalidArgumentException('A szavazás már lezárult.');
        }

        $query = TabloPollVote::where('tablo_poll_id', $poll->id)
            ->where('tablo_guest_session_id', $guest->id);

        if ($optionId) {
            $query->where('tablo_poll_option_id', $optionId);
        }

        $deleted = $query->delete();

        Log::info('Vote(s) removed', [
            'poll_id' => $poll->id,
            'guest_id' => $guest->id,
            'count' => $deleted,
        ]);

        return $deleted;
    }

    /**
     * Szavazás eredmények
     */
    public function getResults(TabloPoll $poll): array
    {
        $options = $poll->options()
            ->withCount('votes')
            ->orderBy('display_order')
            ->get();

        $totalVotes = $options->sum('votes_count');

        return [
            'poll_id' => $poll->id,
            'title' => $poll->title,
            'is_open' => $poll->isOpen(),
            'total_votes' => $totalVotes,
            'unique_voters' => $poll->unique_voters_count,
            'options' => $options->map(fn ($option) => [
                'id' => $option->id,
                'label' => $option->label,
                'description' => $option->description,
                'image_url' => $option->display_image_url,
                'template_id' => $option->tablo_sample_template_id,
                'votes_count' => $option->votes_count,
                'percentage' => $totalVotes > 0 ? round(($option->votes_count / $totalVotes) * 100, 1) : 0,
            ])->toArray(),
        ];
    }

    /**
     * Vendég szavazatai egy poll-ban
     */
    public function getGuestVotes(TabloPoll $poll, TabloGuestSession $guest): array
    {
        return $poll->votes()
            ->where('tablo_guest_session_id', $guest->id)
            ->pluck('tablo_poll_option_id')
            ->toArray();
    }

    /**
     * Vendég szavazatai TÖBB poll-ban egyszerre (N+1 elkerülés).
     *
     * @return array<int, int[]> poll_id => [option_id, ...]
     */
    public function getGuestVotesForPolls(\Illuminate\Support\Collection $polls, TabloGuestSession $guest): array
    {
        $pollIds = $polls->pluck('id');

        return TabloPollVote::whereIn('tablo_poll_id', $pollIds)
            ->where('tablo_guest_session_id', $guest->id)
            ->get()
            ->groupBy('tablo_poll_id')
            ->map(fn ($votes) => $votes->pluck('tablo_poll_option_id')->toArray())
            ->toArray();
    }

    /**
     * Szavazás lezárása
     */
    public function close(TabloPoll $poll): TabloPoll
    {
        $poll->update([
            'is_active' => false,
            'close_at' => now(),
        ]);

        Log::info('Poll closed', ['poll_id' => $poll->id]);

        return $poll;
    }

    /**
     * Szavazás újranyitása
     */
    public function reopen(TabloPoll $poll, ?\DateTime $closeAt = null): TabloPoll
    {
        $poll->update([
            'is_active' => true,
            'close_at' => $closeAt,
        ]);

        Log::info('Poll reopened', ['poll_id' => $poll->id]);

        return $poll;
    }

    /**
     * Projekt összes szavazása (egyszerű, eager loading nélkül)
     */
    public function getByProject(TabloProject $project, bool $activeOnly = false): \Illuminate\Database\Eloquent\Collection
    {
        $query = $project->polls()->with(['options', 'creatorContact']);

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Projekt összes szavazása statisztikákkal (optimalizált, N+1 mentes).
     *
     * Használj ezt a metódust a lista nézethez, ahol szükség van:
     * - votes_count
     * - unique_voters_count
     */
    public function getByProjectWithStats(TabloProject $project, bool $activeOnly = false): \Illuminate\Database\Eloquent\Collection
    {
        $query = $project->polls()
            ->with(['options', 'creatorContact', 'media'])
            ->withCount('votes')
            ->addSelect([
                'unique_voters_count' => TabloPollVote::selectRaw('COUNT(DISTINCT tablo_guest_session_id)')
                    ->whereColumn('tablo_poll_id', 'tablo_polls.id'),
            ]);

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Egy szavazás részletei statisztikákkal (optimalizált).
     */
    public function getWithDetails(int $pollId, int $projectId): ?TabloPoll
    {
        return TabloPoll::with(['options.template', 'media'])
            ->withCount('votes')
            ->addSelect([
                'unique_voters_count' => TabloPollVote::selectRaw('COUNT(DISTINCT tablo_guest_session_id)')
                    ->whereColumn('tablo_poll_id', 'tablo_polls.id'),
            ])
            ->where('id', $pollId)
            ->where('tablo_project_id', $projectId)
            ->first();
    }

    /**
     * Ellenőrzi, hogy az osztálylétszám be van-e állítva az első szavazás előtt.
     *
     * @return bool True ha létrehozható, false ha először osztálylétszám kell
     */
    public function canCreatePoll(TabloProject $project): bool
    {
        // Ha nincs beállítva az osztálylétszám és ez lenne az első szavazás
        if ($project->expected_class_size === null && $project->polls()->count() === 0) {
            return false;
        }

        return true;
    }

    /**
     * Nyertes opció(k) meghatározása
     */
    public function getWinners(TabloPoll $poll): \Illuminate\Database\Eloquent\Collection
    {
        $results = $this->getResults($poll);
        $maxVotes = collect($results['options'])->max('votes_count');

        if ($maxVotes === 0) {
            return collect();
        }

        return $poll->options()
            ->withCount('votes')
            ->having('votes_count', $maxVotes)
            ->get();
    }

    /**
     * Véglegesítéshez használt szavazás eredménye
     */
    public function getFinalizationPollResult(TabloProject $project): ?array
    {
        $poll = $project->polls()
            ->where('use_for_finalization', true)
            ->where('type', TabloPoll::TYPE_TEMPLATE)
            ->latest()
            ->first();

        if (! $poll) {
            return null;
        }

        $winners = $this->getWinners($poll);

        if ($winners->isEmpty()) {
            return null;
        }

        // A legtöbb szavazatot kapott sablon
        $winnerOption = $winners->first();

        return [
            'poll' => $poll,
            'winner_option' => $winnerOption,
            'template_id' => $winnerOption->tablo_sample_template_id,
            'template' => $winnerOption->template,
            'votes_count' => $winnerOption->votes_count,
            'total_votes' => $poll->total_votes,
        ];
    }
}

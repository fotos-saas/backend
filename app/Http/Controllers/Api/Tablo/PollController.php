<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Api\Tablo\Traits\ResolvesTabloProject;
use App\Http\Requests\Api\Tablo\StorePollRequest;
use App\Http\Requests\Api\Tablo\UpdatePollRequest;
use App\Http\Requests\Api\Tablo\VotePollRequest;
use App\Http\Resources\Tablo\PollDetailResource;
use App\Http\Resources\Tablo\PollResource;
use App\Models\TabloPoll;
use App\Services\Tablo\PollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Poll Controller
 *
 * Szavazás kezelés API végpontok.
 * Token-ból azonosítja a projektet.
 */
class PollController extends BaseTabloController
{
    use ResolvesTabloProject;

    public function __construct(
        protected PollService $pollService
    ) {}

    /**
     * Get polls list.
     * GET /api/tablo-frontend/polls
     */
    public function index(Request $request): JsonResponse
    {
        $project = $this->getProjectOrFail($request);
        if ($project instanceof JsonResponse) {
            return $project;
        }

        $activeOnly = $request->boolean('active_only', false);
        $polls = $this->pollService->getByProjectWithStats($project, $activeOnly);

        // Get guest votes if session provided
        $guestSession = $this->getGuestSession($request);
        $guestVotes = [];

        if ($guestSession && ! $guestSession->is_banned) {
            foreach ($polls as $poll) {
                $guestVotes[$poll->id] = $this->pollService->getGuestVotes($poll, $guestSession);
            }
        }

        return $this->successResponse(
            $polls->map(fn ($poll) => PollResource::withGuestVotes($poll, $guestVotes[$poll->id] ?? []))
        );
    }

    /**
     * Get poll details with options.
     * GET /api/tablo-frontend/polls/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $poll = $this->findForProject(TabloPoll::class, $id, $request, 'tablo_project_id', 'Szavazás nem található');
        if ($poll instanceof JsonResponse) {
            return $poll;
        }

        // Get full details
        $poll = $this->pollService->getWithDetails($id, $this->getProjectId($request));

        // Get guest context
        $guestSession = $this->getGuestSession($request);
        $myVotes = [];
        $canVote = false;

        if ($guestSession && ! $guestSession->is_banned) {
            $myVotes = $this->pollService->getGuestVotes($poll, $guestSession);
            $canVote = $poll->canGuestVote($guestSession->id);
        }

        // Get results if allowed
        $showResults = $poll->show_results_before_vote || ! empty($myVotes) || ! $poll->isOpen();
        $results = $showResults ? $this->pollService->getResults($poll) : null;

        $resource = (new PollDetailResource($poll))
            ->withGuestContext($myVotes, $canVote)
            ->withResults($results);

        return $this->successResponse($resource);
    }

    /**
     * Create new poll (contact only).
     * POST /api/tablo-frontend/polls
     */
    public function store(StorePollRequest $request): JsonResponse
    {
        $project = $this->getProjectOrFail($request);
        if ($project instanceof JsonResponse) {
            return $project;
        }

        // Check if class size is set for first poll
        if (! $this->pollService->canCreatePoll($project)) {
            return $this->validationErrorResponse(
                'Először állítsd be az osztálylétszámot!',
                ['requires_class_size' => true]
            );
        }

        $coverImage = $request->file('cover_image');

        $poll = $this->pollService->create(
            $project,
            $request->validated(),
            $this->getContactId($request),
            $coverImage
        );

        // Upload media files (max 5)
        $mediaFiles = $request->file('media', []);
        if (! empty($mediaFiles)) {
            $this->pollService->uploadMediaFiles($poll, $mediaFiles);
        }

        // Reload poll with media
        $poll->load('media');

        return $this->successResponse([
            'id' => $poll->id,
            'title' => $poll->title,
            'cover_image_url' => $poll->cover_image_url,
            'media' => $poll->media->map(fn ($m) => [
                'id' => $m->id,
                'url' => $m->url,
                'fileName' => $m->file_name,
                'sortOrder' => $m->sort_order,
            ]),
        ], 'Szavazás sikeresen létrehozva!', 201);
    }

    /**
     * Update poll (contact only).
     * PUT /api/tablo-frontend/polls/{id}
     */
    public function update(UpdatePollRequest $request, int $id): JsonResponse
    {
        $poll = $this->findForProject(TabloPoll::class, $id, $request, 'tablo_project_id', 'Szavazás nem található');
        if ($poll instanceof JsonResponse) {
            return $poll;
        }

        $this->pollService->update($poll, $request->validated());

        // Delete specified media files
        $deleteMediaIds = $request->input('delete_media_ids', []);
        if (! empty($deleteMediaIds)) {
            $this->pollService->deleteMediaByIds($poll, $deleteMediaIds);
        }

        // Upload new media files
        $mediaFiles = $request->file('media', []);
        if (! empty($mediaFiles)) {
            $this->pollService->uploadMediaFiles($poll, $mediaFiles);
        }

        // Reload poll with media
        $poll->load('media');

        return $this->successResponse([
            'media' => $poll->media->map(fn ($m) => [
                'id' => $m->id,
                'url' => $m->url,
                'fileName' => $m->file_name,
                'sortOrder' => $m->sort_order,
            ]),
        ], 'Szavazás sikeresen frissítve!');
    }

    /**
     * Delete poll (contact only).
     * DELETE /api/tablo-frontend/polls/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $poll = $this->findForProject(TabloPoll::class, $id, $request, 'tablo_project_id', 'Szavazás nem található');
        if ($poll instanceof JsonResponse) {
            return $poll;
        }

        $this->pollService->delete($poll);

        return $this->successResponse(null, 'Szavazás sikeresen törölve!');
    }

    /**
     * Close poll (contact only).
     * POST /api/tablo-frontend/polls/{id}/close
     */
    public function close(Request $request, int $id): JsonResponse
    {
        $poll = $this->findForProject(TabloPoll::class, $id, $request, 'tablo_project_id', 'Szavazás nem található');
        if ($poll instanceof JsonResponse) {
            return $poll;
        }

        if (! $poll->isOpen()) {
            return $this->validationErrorResponse('A szavazás már le van zárva.');
        }

        $this->pollService->close($poll);

        return $this->successResponse(null, 'Szavazás sikeresen lezárva!');
    }

    /**
     * Reopen poll (contact only).
     * POST /api/tablo-frontend/polls/{id}/reopen
     */
    public function reopen(Request $request, int $id): JsonResponse
    {
        $poll = $this->findForProject(TabloPoll::class, $id, $request, 'tablo_project_id', 'Szavazás nem található');
        if ($poll instanceof JsonResponse) {
            return $poll;
        }

        if ($poll->isOpen()) {
            return $this->validationErrorResponse('A szavazás már nyitva van.');
        }

        $closeAt = $request->input('close_at')
            ? new \DateTime($request->input('close_at'))
            : null;

        $this->pollService->reopen($poll, $closeAt);

        return $this->successResponse(null, 'Szavazás sikeresen újranyitva!');
    }

    /**
     * Get poll results.
     * GET /api/tablo-frontend/polls/{id}/results
     */
    public function results(Request $request, int $id): JsonResponse
    {
        $poll = $this->findForProject(TabloPoll::class, $id, $request, 'tablo_project_id', 'Szavazás nem található');
        if ($poll instanceof JsonResponse) {
            return $poll;
        }

        $project = $this->getProjectOrFail($request);
        if ($project instanceof JsonResponse) {
            return $project;
        }

        $results = $this->pollService->getResults($poll);
        $results['participation_rate'] = $project->getPollParticipationRate($poll);

        return $this->successResponse($results);
    }

    /**
     * Cast vote.
     * POST /api/tablo-frontend/polls/{id}/vote
     */
    public function vote(VotePollRequest $request, int $id): JsonResponse
    {
        $poll = $this->findForProject(TabloPoll::class, $id, $request, 'tablo_project_id', 'Szavazás nem található');
        if ($poll instanceof JsonResponse) {
            return $poll;
        }

        // Resolve guest session (required + active)
        $guestSession = $this->requireActiveGuestSession($request);
        if ($guestSession instanceof JsonResponse) {
            return $guestSession;
        }

        try {
            $vote = $this->pollService->vote($poll, $guestSession, $request->validated()['option_id']);

            return $this->successResponse([
                'vote_id' => $vote->id,
                'my_votes' => $this->pollService->getGuestVotes($poll, $guestSession),
                'can_vote_more' => $poll->canGuestVote($guestSession->id),
            ], 'Sikeres szavazat!');
        } catch (\InvalidArgumentException $e) {
            return $this->validationErrorResponse($e->getMessage());
        }
    }

    /**
     * Remove vote.
     * DELETE /api/tablo-frontend/polls/{id}/vote
     */
    public function removeVote(Request $request, int $id): JsonResponse
    {
        $poll = $this->findForProject(TabloPoll::class, $id, $request, 'tablo_project_id', 'Szavazás nem található');
        if ($poll instanceof JsonResponse) {
            return $poll;
        }

        // Resolve guest session (required)
        $guestSession = $this->getGuestSessionOrFail($request);
        if ($guestSession instanceof JsonResponse) {
            return $guestSession;
        }

        try {
            $optionId = $request->input('option_id');
            $removed = $this->pollService->removeVote($poll, $guestSession, $optionId);

            return $this->successResponse([
                'removed_count' => $removed,
                'my_votes' => $this->pollService->getGuestVotes($poll, $guestSession),
            ], 'Szavazat visszavonva!');
        } catch (\InvalidArgumentException $e) {
            return $this->validationErrorResponse($e->getMessage());
        }
    }
}

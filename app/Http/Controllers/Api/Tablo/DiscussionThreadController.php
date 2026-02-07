<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Api\Tablo\Traits\ResolvesTabloProject;
use App\Http\Requests\Api\Tablo\StoreDiscussionRequest;
use App\Http\Requests\Api\Tablo\UpdateDiscussionRequest;
use App\Models\TabloDiscussion;
use App\Services\Tablo\DiscussionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Discussion Thread Controller
 *
 * Fórum beszélgetések (thread-ek) CRUD és moderáció.
 * Token-ból azonosítja a projektet.
 * Üzleti logika és formázás a DiscussionService-ben.
 */
class DiscussionThreadController extends BaseTabloController
{
    use ResolvesTabloProject;

    public function __construct(
        protected DiscussionService $discussionService
    ) {}

    /**
     * Get discussions list with optional filtering.
     * GET /api/tablo-frontend/discussions
     */
    public function index(Request $request): JsonResponse
    {
        $project = $this->getProjectOrFail($request);
        if ($project instanceof JsonResponse) {
            return $project;
        }

        $filters = [
            'search' => $request->get('search'),
            'template_id' => $request->get('template_id'),
            'sort_by' => $request->get('sort_by', 'latest'),
        ];

        $discussions = $this->discussionService->getByProject($project, $filters);

        return $this->successResponse(
            $discussions->map(fn ($d) => $this->discussionService->formatDiscussionSummary($d))
        );
    }

    /**
     * Get discussion details with posts.
     * GET /api/tablo-frontend/discussions/{slug}
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $discussion = $this->findBySlugForProject(
            TabloDiscussion::class,
            $slug,
            $request,
            'tablo_project_id',
            'Beszélgetés nem található'
        );

        if ($discussion instanceof JsonResponse) {
            return $discussion;
        }

        $perPage = $request->integer('per_page', 20);
        $result = $this->discussionService->getWithPosts($discussion, $perPage);

        // Aktuális felhasználó feloldása jogosultság-ellenőrzéshez
        $currentUser = $this->discussionService->resolveCurrentUser(
            $this->isContact($request),
            $this->getContactId($request),
            $this->getGuestSession($request)
        );

        return $this->successResponse([
            'discussion' => $this->discussionService->formatDiscussionDetail($discussion),
            'posts' => $result['posts']->through(
                fn ($post) => $this->discussionService->formatPost($post, $currentUser['type'], $currentUser['id'])
            ),
        ]);
    }

    /**
     * Create new discussion (contact only).
     * POST /api/tablo-frontend/discussions
     */
    public function store(StoreDiscussionRequest $request): JsonResponse
    {
        $project = $this->getProjectOrFail($request);
        if ($project instanceof JsonResponse) {
            return $project;
        }

        $validated = $request->validated();

        $discussion = $this->discussionService->createDiscussion(
            $project,
            $validated['title'],
            $validated['content'],
            TabloDiscussion::CREATOR_TYPE_CONTACT,
            $this->getContactId($request) ?? 0,
            $validated['template_id'] ?? null
        );

        return $this->successResponse([
            'id' => $discussion->id,
            'slug' => $discussion->slug,
        ], 'Beszélgetés sikeresen létrehozva!', 201);
    }

    /**
     * Update discussion (contact only).
     * PUT /api/tablo-frontend/discussions/{id}
     */
    public function update(UpdateDiscussionRequest $request, int $id): JsonResponse
    {
        $discussion = $this->findForProject(
            TabloDiscussion::class,
            $id,
            $request,
            'tablo_project_id',
            'Beszélgetés nem található'
        );

        if ($discussion instanceof JsonResponse) {
            return $discussion;
        }

        $validated = $request->validated();

        $this->discussionService->updateDiscussion($discussion, [
            'title' => $validated['title'] ?? null,
            'tablo_sample_template_id' => $validated['template_id'] ?? $discussion->tablo_sample_template_id,
        ]);

        return $this->successResponse(null, 'Beszélgetés sikeresen frissítve!');
    }

    /**
     * Delete discussion (contact only).
     * DELETE /api/tablo-frontend/discussions/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $discussion = $this->resolveDiscussion($request, $id);
        if ($discussion instanceof JsonResponse) {
            return $discussion;
        }

        $this->discussionService->deleteDiscussion($discussion);

        return $this->successResponse(null, 'Beszélgetés sikeresen törölve!');
    }

    /**
     * Lock discussion (contact only).
     * POST /api/tablo-frontend/discussions/{id}/lock
     */
    public function lock(Request $request, int $id): JsonResponse
    {
        $discussion = $this->resolveDiscussion($request, $id);
        if ($discussion instanceof JsonResponse) {
            return $discussion;
        }

        $this->discussionService->lock($discussion);

        return $this->successResponse(null, 'Beszélgetés lezárva!');
    }

    /**
     * Unlock discussion (contact only).
     * POST /api/tablo-frontend/discussions/{id}/unlock
     */
    public function unlock(Request $request, int $id): JsonResponse
    {
        $discussion = $this->resolveDiscussion($request, $id);
        if ($discussion instanceof JsonResponse) {
            return $discussion;
        }

        $this->discussionService->unlock($discussion);

        return $this->successResponse(null, 'Beszélgetés feloldva!');
    }

    /**
     * Pin discussion (contact only).
     * POST /api/tablo-frontend/discussions/{id}/pin
     */
    public function pin(Request $request, int $id): JsonResponse
    {
        $discussion = $this->resolveDiscussion($request, $id);
        if ($discussion instanceof JsonResponse) {
            return $discussion;
        }

        $this->discussionService->pin($discussion);

        return $this->successResponse(null, 'Beszélgetés kitűzve!');
    }

    /**
     * Unpin discussion (contact only).
     * POST /api/tablo-frontend/discussions/{id}/unpin
     */
    public function unpin(Request $request, int $id): JsonResponse
    {
        $discussion = $this->resolveDiscussion($request, $id);
        if ($discussion instanceof JsonResponse) {
            return $discussion;
        }

        $this->discussionService->unpin($discussion);

        return $this->successResponse(null, 'Beszélgetés levéve a kitűzésből!');
    }

    // ============================================================================
    // PRIVATE HELPERS
    // ============================================================================

    /**
     * Beszélgetés keresése ID alapján az aktuális projekthez.
     * Ismétlődő findForProject hívások összevonása.
     */
    private function resolveDiscussion(Request $request, int $id): TabloDiscussion|JsonResponse
    {
        return $this->findForProject(
            TabloDiscussion::class,
            $id,
            $request,
            'tablo_project_id',
            'Beszélgetés nem található'
        );
    }
}

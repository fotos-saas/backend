<?php

namespace App\Http\Controllers\Api;

use App\Helpers\QueryHelper;
use App\Http\Controllers\Concerns\HasPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BugReport\StoreBugReportCommentRequest;
use App\Http\Requests\Api\BugReport\StoreBugReportRequest;
use App\Models\BugReport;
use App\Services\BugReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Hibajelentések - Partner/Marketer/Csapattag API.
 *
 * A bejelentkezett felhasználó saját hibajelentéseit kezeli.
 */
class BugReportController extends Controller
{
    use HasPagination;

    public function __construct(
        private readonly BugReportService $bugReportService
    ) {}

    /**
     * Saját hibajelentések listája.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BugReport::class);

        $user = auth()->user();
        $perPage = $this->getPerPage($request);
        $sortParams = $this->getSortParams($request, 'created_at', 'desc', [
            'created_at', 'title', 'status', 'priority',
        ]);

        $query = BugReport::forUser($user->id)
            ->with(['attachments']);

        // Keresés
        if ($search = $request->input('search')) {
            $pattern = QueryHelper::safeLikePattern($search);
            $query->where('title', 'ILIKE', $pattern);
        }

        // Státusz szűrés
        if ($status = $request->input('status')) {
            $query->byStatus($status);
        }

        $query->orderBy($sortParams['sort'], $sortParams['direction']);

        $reports = $query->paginate($perPage);

        $reports->getCollection()->transform(fn (BugReport $report) => [
            'id' => $report->id,
            'title' => $report->title,
            'status' => $report->status,
            'status_label' => BugReport::getStatuses()[$report->status] ?? $report->status,
            'priority' => $report->priority,
            'priority_label' => BugReport::getPriorities()[$report->priority] ?? $report->priority,
            'attachments_count' => $report->attachments->count(),
            'created_at' => $report->created_at->toIso8601String(),
            'updated_at' => $report->updated_at->toIso8601String(),
        ]);

        return response()->json($reports);
    }

    /**
     * Hibajelentés részletek.
     */
    public function show(BugReport $bugReport): JsonResponse
    {
        $this->authorize('view', $bugReport);

        $bugReport->load([
            'attachments',
            'comments' => fn ($q) => $q->where('is_internal', false)->with('author:id,name'),
            'statusHistory.changedByUser:id,name',
            'reporter:id,name,email',
        ]);

        return response()->json([
            'id' => $bugReport->id,
            'title' => $bugReport->title,
            'description' => $bugReport->description,
            'status' => $bugReport->status,
            'status_label' => BugReport::getStatuses()[$bugReport->status] ?? $bugReport->status,
            'priority' => $bugReport->priority,
            'priority_label' => BugReport::getPriorities()[$bugReport->priority] ?? $bugReport->priority,
            'created_at' => $bugReport->created_at->toIso8601String(),
            'updated_at' => $bugReport->updated_at->toIso8601String(),
            'attachments' => $bugReport->attachments->map(fn ($a) => [
                'id' => $a->id,
                'url' => $a->getUrl(),
                'original_filename' => $a->original_filename,
                'formatted_size' => $a->getFormattedSize(),
                'width' => $a->width,
                'height' => $a->height,
            ]),
            'comments' => $bugReport->comments->map(fn ($c) => [
                'id' => $c->id,
                'content' => $c->content,
                'author' => [
                    'id' => $c->author->id,
                    'name' => $c->author->name,
                ],
                'created_at' => $c->created_at->toIso8601String(),
            ]),
            'status_history' => $bugReport->statusHistory->map(fn ($h) => [
                'id' => $h->id,
                'old_status' => $h->old_status,
                'new_status' => $h->new_status,
                'new_status_label' => BugReport::getStatuses()[$h->new_status] ?? $h->new_status,
                'note' => $h->note,
                'changed_by' => $h->changedByUser ? [
                    'id' => $h->changedByUser->id,
                    'name' => $h->changedByUser->name,
                ] : null,
                'created_at' => $h->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Új hibajelentés létrehozása.
     */
    public function store(StoreBugReportRequest $request): JsonResponse
    {
        $this->authorize('create', BugReport::class);

        $report = $this->bugReportService->createReport(
            auth()->user(),
            $request->validated(),
            $request->file('attachments', [])
        );

        return response()->json([
            'message' => 'Hibajelentés sikeresen létrehozva.',
            'id' => $report->id,
        ], 201);
    }

    /**
     * Komment hozzáadása.
     */
    public function addComment(StoreBugReportCommentRequest $request, BugReport $bugReport): JsonResponse
    {
        $this->authorize('addComment', $bugReport);

        $comment = $this->bugReportService->addComment(
            $bugReport,
            auth()->user(),
            $request->validated('content'),
            false
        );

        return response()->json([
            'message' => 'Hozzászólás sikeresen elküldve.',
            'comment' => [
                'id' => $comment->id,
                'content' => $comment->content,
                'author' => [
                    'id' => $comment->author->id,
                    'name' => $comment->author->name,
                ],
                'created_at' => $comment->created_at->toIso8601String(),
            ],
        ], 201);
    }
}

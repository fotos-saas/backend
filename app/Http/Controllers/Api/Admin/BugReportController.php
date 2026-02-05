<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\QueryHelper;
use App\Http\Controllers\Concerns\HasPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BugReport\StoreBugReportCommentRequest;
use App\Http\Requests\Api\BugReport\UpdateBugReportPriorityRequest;
use App\Http\Requests\Api\BugReport\UpdateBugReportStatusRequest;
use App\Models\BugReport;
use App\Services\BugReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Hibajelentések - SuperAdmin API.
 *
 * Minden bejelentés kezelése, státusz/prioritás módosítás, belső kommentek.
 */
class BugReportController extends Controller
{
    use HasPagination;

    public function __construct(
        private readonly BugReportService $bugReportService
    ) {}

    /**
     * Minden hibajelentés listája.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $sortParams = $this->getSortParams($request, 'created_at', 'desc', [
            'created_at', 'title', 'status', 'priority',
        ]);

        $query = BugReport::with(['reporter:id,name,email']);

        // Keresés
        if ($search = $request->input('search')) {
            $pattern = QueryHelper::safeLikePattern($search);
            $query->where('title', 'ILIKE', $pattern);
        }

        // Státusz szűrés
        if ($status = $request->input('status')) {
            $query->byStatus($status);
        }

        // Prioritás szűrés
        if ($priority = $request->input('priority')) {
            $query->byPriority($priority);
        }

        // Csak olvasatlanok
        if ($request->boolean('unread_only')) {
            $query->unread();
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
            'answered_by' => $report->answered_by,
            'first_viewed_at' => $report->first_viewed_at?->toIso8601String(),
            'reporter' => $report->reporter ? [
                'id' => $report->reporter->id,
                'name' => $report->reporter->name,
                'email' => $report->reporter->email,
            ] : null,
            'created_at' => $report->created_at->toIso8601String(),
            'updated_at' => $report->updated_at->toIso8601String(),
        ]);

        return response()->json($reports);
    }

    /**
     * Hibajelentés részletek + markAsViewed.
     */
    public function show(BugReport $bugReport): JsonResponse
    {
        $bugReport->markAsViewed();

        $bugReport->load([
            'attachments',
            'comments.author:id,name',
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
            'answered_by' => $bugReport->answered_by,
            'ai_response' => $bugReport->ai_response,
            'first_viewed_at' => $bugReport->first_viewed_at?->toIso8601String(),
            'reporter' => $bugReport->reporter ? [
                'id' => $bugReport->reporter->id,
                'name' => $bugReport->reporter->name,
                'email' => $bugReport->reporter->email,
            ] : null,
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
                'is_internal' => $c->is_internal,
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
     * Státusz módosítás.
     */
    public function updateStatus(UpdateBugReportStatusRequest $request, BugReport $bugReport): JsonResponse
    {
        $this->authorize('updateStatus', $bugReport);

        $report = $this->bugReportService->updateStatus(
            $bugReport,
            $request->validated('status'),
            auth()->user(),
            $request->validated('note')
        );

        return response()->json([
            'message' => 'Státusz sikeresen módosítva.',
            'status' => $report->status,
            'status_label' => BugReport::getStatuses()[$report->status] ?? $report->status,
        ]);
    }

    /**
     * Prioritás módosítás.
     */
    public function updatePriority(UpdateBugReportPriorityRequest $request, BugReport $bugReport): JsonResponse
    {
        $this->authorize('update', $bugReport);

        $report = $this->bugReportService->updatePriority(
            $bugReport,
            $request->validated('priority')
        );

        return response()->json([
            'message' => 'Prioritás sikeresen módosítva.',
            'priority' => $report->priority,
            'priority_label' => BugReport::getPriorities()[$report->priority] ?? $report->priority,
        ]);
    }

    /**
     * Komment hozzáadása (is_internal támogatással).
     */
    public function addComment(StoreBugReportCommentRequest $request, BugReport $bugReport): JsonResponse
    {
        $comment = $this->bugReportService->addComment(
            $bugReport,
            auth()->user(),
            $request->validated('content'),
            $request->boolean('is_internal', false)
        );

        return response()->json([
            'message' => 'Hozzászólás sikeresen elküldve.',
            'comment' => [
                'id' => $comment->id,
                'content' => $comment->content,
                'is_internal' => $comment->is_internal,
                'author' => [
                    'id' => $comment->author->id,
                    'name' => $comment->author->name,
                ],
                'created_at' => $comment->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Olvasatlan hibajelentések száma.
     */
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'count' => $this->bugReportService->getUnreadCount(),
        ]);
    }
}

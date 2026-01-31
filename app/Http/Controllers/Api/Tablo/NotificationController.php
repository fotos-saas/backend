<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Controller;
use App\Models\TabloGuestSession;
use App\Models\TabloNotification;
use App\Services\Tablo\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notification Controller
 *
 * Értesítések API végpontok.
 */
class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Get notifications for current user.
     * GET /api/tablo-frontend/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        // Get recipient info
        [$recipientType, $recipientId] = $this->getRecipientInfo($request, $projectId);

        if (! $recipientType || ! $recipientId) {
            // Ha nincs érvényes session, üres listát adunk vissza (nem 401!)
            return response()->json([
                'success' => true,
                'data' => [
                    'notifications' => [],
                    'unread_count' => 0,
                ],
            ]);
        }

        $limit = $request->integer('limit', 50);
        $unreadOnly = $request->boolean('unread_only', false);

        $notifications = $this->notificationService->getNotifications(
            $projectId,
            $recipientType,
            $recipientId,
            $limit,
            $unreadOnly
        );

        $unreadCount = $this->notificationService->getUnreadCount(
            $projectId,
            $recipientType,
            $recipientId
        );

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $notifications->map(fn ($n) => $this->formatNotification($n)),
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    /**
     * Get unread count.
     * GET /api/tablo-frontend/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        [$recipientType, $recipientId] = $this->getRecipientInfo($request, $projectId);

        if (! $recipientType || ! $recipientId) {
            // Ha nincs érvényes session, 0-t adunk vissza (nem 401!)
            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => 0,
                ],
            ]);
        }

        $count = $this->notificationService->getUnreadCount(
            $projectId,
            $recipientType,
            $recipientId
        );

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }

    /**
     * Mark notification as read.
     * POST /api/tablo-frontend/notifications/{id}/read
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        [$recipientType, $recipientId] = $this->getRecipientInfo($request, $projectId);

        if (! $recipientType || ! $recipientId) {
            return response()->json([
                'success' => false,
                'message' => 'Érvénytelen session.',
            ], 401);
        }

        $notification = TabloNotification::where('id', $id)
            ->where('tablo_project_id', $projectId)
            ->forRecipient($recipientType, $recipientId)
            ->first();

        if (! $notification) {
            return response()->json([
                'success' => false,
                'message' => 'Értesítés nem található.',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Értesítés olvasottnak jelölve.',
        ]);
    }

    /**
     * Mark all notifications as read.
     * POST /api/tablo-frontend/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        [$recipientType, $recipientId] = $this->getRecipientInfo($request, $projectId);

        if (! $recipientType || ! $recipientId) {
            return response()->json([
                'success' => false,
                'message' => 'Érvénytelen session.',
            ], 401);
        }

        $this->notificationService->markAllAsRead($projectId, $recipientType, $recipientId);

        return response()->json([
            'success' => true,
            'message' => 'Összes értesítés olvasottnak jelölve.',
        ]);
    }

    /**
     * Get recipient info from request.
     *
     * @return array{0: string|null, 1: int|null}
     */
    private function getRecipientInfo(Request $request, int $projectId): array
    {
        $guestSessionToken = $request->header('X-Guest-Session');

        if ($guestSessionToken) {
            $guestSession = TabloGuestSession::findByTokenAndProject($guestSessionToken, $projectId);
            if ($guestSession && ! $guestSession->is_banned) {
                return ['guest', $guestSession->id];
            }
        }

        // Contact from token
        $token = $request->user()->currentAccessToken();
        if ($token->contact_id) {
            return ['contact', $token->contact_id];
        }

        return [null, null];
    }

    /**
     * Format notification for API response.
     */
    private function formatNotification(TabloNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'data' => $notification->data,
            'action_url' => $notification->action_url,
            'is_read' => $notification->is_read,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at->toIso8601String(),
        ];
    }
}

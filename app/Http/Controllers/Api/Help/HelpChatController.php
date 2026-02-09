<?php

namespace App\Http\Controllers\Api\Help;

use App\Actions\Help\SendChatMessageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Help\SendChatMessageRequest;
use App\Models\HelpChatConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpChatController extends Controller
{
    /**
     * Chat üzenet küldése.
     */
    public function send(SendChatMessageRequest $request, SendChatMessageAction $action): JsonResponse
    {
        $user = $request->user();
        $result = $action->execute(
            $user,
            $request->validated('message'),
            $request->validated('conversation_id'),
            $request->validated('context_route'),
        );

        if (! $result['success']) {
            return $this->errorResponse($result['message'], 429, $result['data'] ?? []);
        }

        return $this->successResponse($result['data'], $result['message']);
    }

    /**
     * Beszélgetések listázása.
     */
    public function conversations(Request $request): JsonResponse
    {
        $conversations = HelpChatConversation::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'title', 'context_route', 'message_count', 'created_at', 'updated_at']);

        return $this->successResponse($conversations);
    }

    /**
     * Beszélgetés üzenetei.
     */
    public function messages(Request $request, HelpChatConversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return $this->forbiddenResponse();
        }

        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'created_at']);

        return $this->successResponse($messages);
    }

    /**
     * Beszélgetés törlése.
     */
    public function destroy(Request $request, HelpChatConversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return $this->forbiddenResponse();
        }

        $conversation->delete();

        return $this->successMessageResponse('Beszélgetés törölve');
    }
}

<?php

namespace App\Actions\Help;

use App\Models\HelpChatConversation;
use App\Models\HelpChatMessage;
use App\Models\User;
use App\Services\Help\HelpChatbotService;
use App\Services\Help\HelpUsageLimiterService;

class SendChatMessageAction
{
    public function __construct(
        private HelpChatbotService $chatbotService,
        private HelpUsageLimiterService $usageLimiter,
        private BuildChatContextAction $buildContext,
    ) {}

    /**
     * Chat üzenet küldése és válasz generálása.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function execute(User $user, string $message, ?int $conversationId = null, ?string $contextRoute = null): array
    {
        $role = $this->resolveRole($user);
        $plan = $this->resolvePlan($user);
        $partnerId = $user->partner_id ?? $user->getEffectivePartner()?->id;

        // Limit ellenőrzés
        $limitCheck = $this->usageLimiter->checkLimit($user->id, $partnerId, $plan);
        if (! $limitCheck['allowed']) {
            return [
                'success' => false,
                'message' => $limitCheck['reason'],
                'data' => ['usage' => $limitCheck['usage']],
            ];
        }

        // Conversation lekérés vagy létrehozás
        $conversation = $this->getOrCreateConversation($user, $conversationId, $role, $plan, $contextRoute);

        // User üzenet mentése
        HelpChatMessage::create([
            'help_chat_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $message,
        ]);

        // Üzenet előzmények (max 10)
        $history = $conversation->messages()
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->reverse()
            ->values();

        $messages = $history->map(fn (HelpChatMessage $msg) => [
            'role' => $msg->role,
            'content' => $msg->content,
        ])->toArray();

        // System prompt
        $systemPrompt = $this->buildContext->execute($user, $contextRoute);

        // Claude hívás
        try {
            $response = $this->chatbotService->chat($messages, $systemPrompt);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Sajnálom, jelenleg nem tudok válaszolni. Kérlek próbáld újra később!',
            ];
        }

        $inputTokens = $response['usage']['input_tokens'] ?? 0;
        $outputTokens = $response['usage']['output_tokens'] ?? 0;

        // Assistant válasz mentése
        $assistantMessage = HelpChatMessage::create([
            'help_chat_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $response['content'],
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ]);

        // Conversation title (első üzenetből)
        if (! $conversation->title) {
            $conversation->update(['title' => mb_substr($message, 0, 100)]);
        }

        // Token usage tracking
        $conversation->addTokenUsage($inputTokens, $outputTokens);
        $this->usageLimiter->recordUsage($user->id, $partnerId, $inputTokens, $outputTokens);

        return [
            'success' => true,
            'message' => 'OK',
            'data' => [
                'conversation_id' => $conversation->id,
                'assistant_message' => [
                    'id' => $assistantMessage->id,
                    'content' => $assistantMessage->content,
                    'created_at' => $assistantMessage->created_at,
                ],
                'usage' => $limitCheck['usage'],
            ],
        ];
    }

    private function getOrCreateConversation(User $user, ?int $conversationId, string $role, string $plan, ?string $route): HelpChatConversation
    {
        if ($conversationId) {
            $conversation = HelpChatConversation::where('id', $conversationId)
                ->where('user_id', $user->id)
                ->first();

            if ($conversation) {
                if ($route && $conversation->context_route !== $route) {
                    $conversation->update(['context_route' => $route]);
                }

                return $conversation;
            }
        }

        return HelpChatConversation::create([
            'user_id' => $user->id,
            'context_role' => $role,
            'context_plan' => $plan,
            'context_route' => $route,
        ]);
    }

    private function resolveRole(User $user): string
    {
        if ($user->is_super_admin) {
            return 'super_admin';
        }

        return $user->role ?? 'guest';
    }

    private function resolvePlan(User $user): string
    {
        if ($user->partner_id) {
            return $user->partner?->plan ?? 'alap';
        }

        $effectivePartner = $user->getEffectivePartner();

        return $effectivePartner?->plan ?? 'client';
    }
}

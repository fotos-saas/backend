<?php

namespace App\Services\Help;

use App\Models\HelpChatUsage;

class HelpUsageLimiterService
{
    /**
     * Napi limit ellenőrzése.
     *
     * @return array{allowed: bool, reason: string|null, usage: array}
     */
    public function checkLimit(int $userId, ?int $partnerId = null, ?string $plan = null): array
    {
        $limits = $this->getLimitsForPlan($plan);
        $usage = HelpChatUsage::getOrCreateToday($userId, $partnerId);

        $totalTokens = $usage->totalTokens();

        if ($usage->message_count >= $limits['daily_messages']) {
            return [
                'allowed' => false,
                'reason' => "Elérted a napi üzenetlimitet ({$limits['daily_messages']} üzenet). Holnap újra írhatsz!",
                'usage' => $this->formatUsage($usage, $limits),
            ];
        }

        if ($totalTokens >= $limits['daily_tokens']) {
            return [
                'allowed' => false,
                'reason' => 'Elérted a napi tokenlimitet. Holnap újra írhatsz!',
                'usage' => $this->formatUsage($usage, $limits),
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
            'usage' => $this->formatUsage($usage, $limits),
        ];
    }

    /**
     * Használat rögzítése.
     */
    public function recordUsage(int $userId, ?int $partnerId, int $inputTokens, int $outputTokens): void
    {
        $usage = HelpChatUsage::getOrCreateToday($userId, $partnerId);
        $usage->addUsage($inputTokens, $outputTokens);
    }

    /**
     * Plan alapú limitek.
     */
    private function getLimitsForPlan(?string $plan): array
    {
        $defaults = [
            'alap' => ['daily_messages' => 20, 'daily_tokens' => 50_000],
            'iskola' => ['daily_messages' => 50, 'daily_tokens' => 150_000],
            'studio' => ['daily_messages' => 100, 'daily_tokens' => 300_000],
            'vip' => ['daily_messages' => 200, 'daily_tokens' => 600_000],
            'client' => ['daily_messages' => 10, 'daily_tokens' => 25_000],
        ];

        $configLimits = config("plans.plans.{$plan}.help_limits");

        if ($configLimits) {
            return $configLimits;
        }

        return $defaults[$plan] ?? $defaults['client'];
    }

    private function formatUsage(HelpChatUsage $usage, array $limits): array
    {
        return [
            'messages_used' => $usage->message_count,
            'messages_limit' => $limits['daily_messages'],
            'tokens_used' => $usage->totalTokens(),
            'tokens_limit' => $limits['daily_tokens'],
        ];
    }
}

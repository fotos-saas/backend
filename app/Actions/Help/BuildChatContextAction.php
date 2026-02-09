<?php

namespace App\Actions\Help;

use App\Models\User;
use App\Services\Help\HelpChatbotService;

class BuildChatContextAction
{
    public function __construct(
        private HelpChatbotService $chatbotService,
    ) {}

    /**
     * System prompt összeállítása user kontextus alapján.
     */
    public function execute(User $user, ?string $route = null): string
    {
        $role = $this->resolveRole($user);
        $plan = $this->resolvePlan($user);
        $featureKeys = $this->resolveFeatureKeys($plan);

        return $this->chatbotService->buildSystemPrompt($role, $plan, $route, $featureKeys);
    }

    private function resolveRole(User $user): string
    {
        if ($user->is_super_admin) {
            return 'super_admin';
        }

        $role = $user->role ?? 'guest';

        // Csapattag role-ok normalizálása
        if (in_array($role, ['designer', 'marketer', 'printer', 'assistant'])) {
            return $role;
        }

        if ($user->partner_id || $role === 'partner') {
            return 'partner';
        }

        return 'guest';
    }

    private function resolvePlan(User $user): string
    {
        // Partner user: saját partner plan-je
        if ($user->partner_id) {
            $partner = $user->partner;
            if ($partner) {
                return $partner->plan ?? 'alap';
            }
        }

        // Csapattag: főnök plan-je
        $effectivePartner = $user->getEffectivePartner();
        if ($effectivePartner) {
            return $effectivePartner->plan ?? 'alap';
        }

        // Guest/client: nincs plan
        return 'client';
    }

    private function resolveFeatureKeys(string $plan): array
    {
        return config("plans.plans.{$plan}.feature_keys", []);
    }
}

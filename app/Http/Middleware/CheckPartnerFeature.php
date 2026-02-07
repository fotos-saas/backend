<?php

namespace App\Http\Middleware;

use App\Models\Partner;
use App\Models\TabloPartner;
use App\Models\TabloProject;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check if partner has a specific feature enabled.
 *
 * Supports two contexts:
 * 1. Partner dashboard (role:partner) - checks Partner model features/addons
 * 2. Tablo frontend (tablo_project_id token) - checks TabloPartner features
 *    AND the subscriber Partner's addons (for forum/polls)
 *
 * Usage in routes:
 * Route::middleware('partner.feature:client_orders')->group(...)
 * Route::middleware('partner.feature:polls')->group(...)
 */
class CheckPartnerFeature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs bejelentkezve.',
            ], 401);
        }

        $token = $user->currentAccessToken();

        // 1. Tablo frontend context - check TabloProject's partner
        if ($token && $token->tablo_project_id) {
            return $this->checkTabloProjectFeature($request, $next, $feature, $token->tablo_project_id);
        }

        // 2. Partner dashboard context - check Partner model (subscriber)
        if ($user->partner) {
            return $this->checkSubscriberPartnerFeature($request, $next, $feature, $user->partner);
        }

        // 3. Legacy: TabloPartner context (ügyintéző without subscription)
        if ($user->tablo_partner_id) {
            return $this->checkTabloPartnerFeature($request, $next, $feature, $user->tablo_partner_id);
        }

        return response()->json([
            'success' => false,
            'message' => 'Nincs partnerhez rendelve.',
        ], 403);
    }

    /**
     * Check feature for Tablo frontend (TabloProject context)
     */
    private function checkTabloProjectFeature(Request $request, Closure $next, string $feature, int $projectId): Response
    {
        $project = TabloProject::with('partner.users.partner')->find($projectId);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'A projekt nem található.',
            ], 404);
        }

        $tabloPartner = $project->partner;

        if (! $tabloPartner) {
            return response()->json([
                'success' => false,
                'message' => 'A projekt nincs partnerhez rendelve.',
            ], 403);
        }

        // Check TabloPartner's own features first (directly set)
        if ($tabloPartner->hasFeature($feature)) {
            return $next($request);
        }

        // For forum/polls features, also check if subscriber Partner has addon
        if (in_array($feature, ['forum', 'polls'])) {
            $subscriberPartner = $this->findSubscriberPartner($tabloPartner);

            if ($subscriberPartner && $subscriberPartner->hasFeature($feature)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Ez a funkció nem elérhető. A projekt tulajdonosának elő kell fizetnie rá.',
            'upgrade_required' => true,
            'feature' => $feature,
        ], 403);
    }

    /**
     * Check feature for Partner subscriber (dashboard context)
     */
    private function checkSubscriberPartnerFeature(Request $request, Closure $next, string $feature, Partner $partner): Response
    {
        if ($partner->hasFeature($feature)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Ez a funkció nem elérhető a jelenlegi csomagodban.',
            'upgrade_required' => true,
            'feature' => $feature,
        ], 403);
    }

    /**
     * Check feature for TabloPartner (legacy ügyintéző context)
     */
    private function checkTabloPartnerFeature(Request $request, Closure $next, string $feature, int $tabloPartnerId): Response
    {
        $tabloPartner = TabloPartner::find($tabloPartnerId);

        if (! $tabloPartner) {
            return response()->json([
                'success' => false,
                'message' => 'A partner nem található.',
            ], 404);
        }

        // Check TabloPartner's own features
        if ($tabloPartner->hasFeature($feature)) {
            return $next($request);
        }

        // Check subscriber Partner for delegated features (forum, polls, branding, client_orders)
        $subscriberPartner = $this->findSubscriberPartner($tabloPartner);

        if ($subscriberPartner && $subscriberPartner->hasFeature($feature)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Ez a funkció nincs engedélyezve a partnered számára.',
        ], 403);
    }

    /**
     * Find the subscriber Partner associated with a TabloPartner
     *
     * Priority: direct FK (partner_id) → Users → Partner (via user_id)
     */
    private function findSubscriberPartner(TabloPartner $tabloPartner): ?Partner
    {
        // 1. Direct FK (new: tablo_partners.partner_id → partners.id)
        if ($tabloPartner->subscriptionPartner) {
            return $tabloPartner->subscriptionPartner;
        }

        // 2. Fallback: Find a User that belongs to this TabloPartner and has a Partner subscription
        $userWithSubscription = $tabloPartner->users()
            ->whereHas('partner', function ($query) {
                $query->whereIn('subscription_status', ['active', 'paused']);
            })
            ->first();

        return $userWithSubscription?->partner;
    }
}

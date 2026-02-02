<?php

namespace App\Services\SuperAdmin;

use App\Helpers\QueryHelper;
use App\Models\AdminAuditLog;
use App\Models\Partner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Subscriber Service
 *
 * Kezeli a subscriber (Partner) lekérdezéseket és formázást:
 * - Lista lekérés szűrőkkel
 * - Részletes adatok formázása
 * - Audit log kezelés
 */
class SubscriberService
{
    /**
     * Csomag ár lekérése config-ból (Single Source of Truth)
     */
    public function getPlanPrice(string $plan, string $cycle): int
    {
        $priceKey = $cycle === 'yearly' ? 'yearly_price' : 'monthly_price';

        return (int) config("plans.plans.{$plan}.{$priceKey}", 0);
    }

    /**
     * Csomag név lekérése config-ból (Single Source of Truth)
     */
    public function getPlanName(string $plan): string
    {
        $fullName = config("plans.plans.{$plan}.name", ucfirst($plan));

        return str_replace('TablóStúdió ', '', $fullName);
    }

    /**
     * Subscriber lista lekérése szűrőkkel
     */
    public function getFilteredList(Request $request): LengthAwarePaginator
    {
        $perPage = $request->input('per_page', 18);
        $search = $request->input('search');
        $plan = $request->input('plan');
        $status = $request->input('status');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $query = Partner::query()
            ->join('users', 'partners.user_id', '=', 'users.id')
            ->select([
                'partners.*',
                'users.name as user_name',
                'users.email as user_email',
            ]);

        // Search - ILIKE pattern escape-eléssel a DoS támadás ellen
        if ($search) {
            $safePattern = QueryHelper::safeLikePattern($search);
            $query->where(function ($q) use ($safePattern) {
                $q->where('users.name', 'ilike', $safePattern)
                    ->orWhere('users.email', 'ilike', $safePattern)
                    ->orWhere('partners.company_name', 'ilike', $safePattern);
            });
        }

        // Plan filter
        if ($plan && in_array($plan, ['alap', 'iskola', 'studio'])) {
            $query->where('partners.plan', $plan);
        }

        // Status filter
        if ($status && in_array($status, ['active', 'paused', 'canceling', 'trial'])) {
            $query->where('partners.subscription_status', $status);
        }

        // Sorting
        $allowedSortFields = [
            'name' => 'users.name',
            'email' => 'users.email',
            'plan' => 'partners.plan',
            'subscription_ends_at' => 'partners.subscription_ends_at',
            'created_at' => 'partners.created_at',
        ];

        $sortColumn = $allowedSortFields[$sortBy] ?? 'partners.created_at';
        $query->orderBy($sortColumn, $sortDir === 'asc' ? 'asc' : 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Subscriber formázása lista nézethez
     */
    public function formatForList(Partner $partner): array
    {
        $billingCycle = $partner->billing_cycle ?? 'monthly';
        $plan = $partner->plan ?? 'alap';

        return [
            'id' => $partner->id,
            'name' => $partner->user_name,
            'email' => $partner->user_email,
            'companyName' => $partner->company_name,
            'plan' => $plan,
            'planName' => $this->getPlanName($plan),
            'billingCycle' => $billingCycle,
            'price' => $this->getPlanPrice($plan, $billingCycle),
            'subscriptionStatus' => $partner->subscription_status ?? 'trial',
            'subscriptionEndsAt' => $partner->subscription_ends_at?->toIso8601String(),
            'createdAt' => $partner->created_at?->toIso8601String(),
        ];
    }

    /**
     * Subscriber formázása részletes nézethez
     */
    public function formatForDetail(Partner $partner): array
    {
        $billingCycle = $partner->billing_cycle ?? 'monthly';
        $plan = $partner->plan ?? 'alap';

        // Calculate trial days remaining
        $trialDaysRemaining = null;
        if ($partner->subscription_status === 'trial' && $partner->subscription_ends_at) {
            $trialDaysRemaining = max(0, now()->diffInDays($partner->subscription_ends_at, false));
        }

        return [
            'id' => $partner->id,
            'name' => $partner->user->name,
            'email' => $partner->user->email,
            'companyName' => $partner->company_name,
            'taxNumber' => $partner->tax_number,
            'billingCountry' => $partner->billing_country,
            'billingPostalCode' => $partner->billing_postal_code,
            'billingCity' => $partner->billing_city,
            'billingAddress' => $partner->billing_address,
            'phone' => $partner->phone,
            'plan' => $plan,
            'planName' => $this->getPlanName($plan),
            'billingCycle' => $billingCycle,
            'price' => $this->getPlanPrice($plan, $billingCycle),
            'subscriptionStatus' => $partner->subscription_status ?? 'trial',
            'subscriptionStartedAt' => $partner->subscription_started_at?->toIso8601String(),
            'subscriptionEndsAt' => $partner->subscription_ends_at?->toIso8601String(),
            'trialDaysRemaining' => $trialDaysRemaining,
            'stripeCustomerId' => $partner->stripe_customer_id,
            'stripeSubscriptionId' => $partner->stripe_subscription_id,
            'storageLimitGb' => $partner->storage_limit_gb,
            'maxClasses' => $partner->max_classes,
            'features' => $partner->features,
            'createdAt' => $partner->created_at?->toIso8601String(),
            'activeDiscount' => $partner->activeDiscount ? [
                'percent' => $partner->activeDiscount->percent,
                'validUntil' => $partner->activeDiscount->valid_until?->toIso8601String(),
                'note' => $partner->activeDiscount->note,
                'createdAt' => $partner->activeDiscount->created_at?->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * Audit log lekérése subscriber-hez
     */
    public function getAuditLogs(int $partnerId, Request $request): LengthAwarePaginator
    {
        $perPage = $request->input('per_page', 20);

        $query = AdminAuditLog::with('adminUser')
            ->where('target_partner_id', $partnerId);

        // Keresés admin név alapján - ILIKE pattern escape-eléssel
        if ($search = $request->input('search')) {
            $safePattern = QueryHelper::safeLikePattern($search);
            $query->whereHas('adminUser', function ($q) use ($safePattern) {
                $q->where('name', 'ilike', $safePattern);
            });
        }

        // Művelet típus szűrő
        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        // Hide view actions by default (unless explicitly requested)
        if (! $request->boolean('show_views', false)) {
            $query->where('action', '!=', AdminAuditLog::ACTION_VIEW);
        }

        // Rendezés
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy('created_at', $sortDir === 'asc' ? 'asc' : 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Audit log formázása
     */
    public function formatAuditLog(AdminAuditLog $log): array
    {
        return [
            'id' => $log->id,
            'adminName' => $log->adminUser->name ?? 'Ismeretlen',
            'action' => $log->action,
            'actionLabel' => $log->action_label,
            'details' => $log->details,
            'ipAddress' => $log->ip_address,
            'createdAt' => $log->created_at->toIso8601String(),
        ];
    }

    /**
     * Partner csomag adatok frissítése
     */
    public function updatePlan(Partner $partner, string $newPlan, string $newBillingCycle): void
    {
        $planConfig = Partner::PLANS[$newPlan];

        $partner->update([
            'plan' => $newPlan,
            'billing_cycle' => $newBillingCycle,
            'storage_limit_gb' => $planConfig['storage_limit_gb'],
            'max_classes' => $planConfig['max_classes'],
            'features' => $planConfig['features'],
        ]);
    }

    /**
     * Partner subscription státusz frissítése cancel után
     */
    public function updateCancelStatus(Partner $partner, bool $immediate): void
    {
        if ($immediate) {
            $partner->update([
                'subscription_status' => 'canceled',
                'stripe_subscription_id' => null,
            ]);
        } else {
            $partner->update([
                'subscription_status' => 'canceling',
            ]);
        }
    }
}

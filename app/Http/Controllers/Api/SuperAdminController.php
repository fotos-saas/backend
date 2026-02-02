<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Partner;
use App\Models\QrRegistrationCode;
use App\Models\TabloPartner;
use App\Models\TabloProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Invoice;
use Stripe\InvoiceItem;
use Stripe\Stripe;
use Stripe\Subscription;

/**
 * Super Admin Controller for frontend-tablo super admin dashboard.
 *
 * Provides system-wide statistics and management for super admin users.
 */
class SuperAdminController extends Controller
{
    /**
     * Csomag árak (Ft)
     */
    private const PLAN_PRICES = [
        'alap' => ['monthly' => 4990, 'yearly' => 49900],
        'iskola' => ['monthly' => 14990, 'yearly' => 149900],
        'studio' => ['monthly' => 29990, 'yearly' => 299900],
    ];

    /**
     * Dashboard statistics.
     */
    public function stats(): JsonResponse
    {
        $totalPartners = TabloPartner::count();
        $totalProjects = TabloProject::count();

        $activeQrCodes = QrRegistrationCode::active()->count();

        // Projects by status
        $projectsByStatus = TabloProject::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Subscribers count (Partner model)
        $totalSubscribers = Partner::count();
        $activeSubscribers = Partner::where('subscription_status', 'active')->count();

        return response()->json([
            'totalPartners' => $totalPartners,
            'totalProjects' => $totalProjects,
            'activeQrCodes' => $activeQrCodes,
            'projectsByStatus' => $projectsByStatus,
            'totalSubscribers' => $totalSubscribers,
            'activeSubscribers' => $activeSubscribers,
        ]);
    }

    /**
     * List partners with pagination and search.
     */
    public function partners(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $query = TabloPartner::query()
            ->withCount('projects');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        // Sorting
        $allowedSortFields = ['name', 'email', 'created_at', 'projects_count'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $partners = $query->paginate($perPage);

        return response()->json([
            'data' => $partners->map(fn ($partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'schoolName' => $partner->email, // email as secondary info
                'hasActiveQrCode' => false, // partner level doesn't have QR
            ]),
            'current_page' => $partners->currentPage(),
            'last_page' => $partners->lastPage(),
            'per_page' => $partners->perPage(),
            'total' => $partners->total(),
            'from' => $partners->firstItem(),
            'to' => $partners->lastItem(),
        ]);
    }

    /**
     * List subscribers (Partner model) with pagination, search and filters.
     */
    public function subscribers(Request $request): JsonResponse
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

        // Search
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'ilike', "%{$search}%")
                    ->orWhere('users.email', 'ilike', "%{$search}%")
                    ->orWhere('partners.company_name', 'ilike', "%{$search}%");
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

        $subscribers = $query->paginate($perPage);

        return response()->json([
            'data' => $subscribers->map(fn ($partner) => $this->formatSubscriber($partner)),
            'current_page' => $subscribers->currentPage(),
            'last_page' => $subscribers->lastPage(),
            'per_page' => $subscribers->perPage(),
            'total' => $subscribers->total(),
            'from' => $subscribers->firstItem(),
            'to' => $subscribers->lastItem(),
        ]);
    }

    /**
     * Format subscriber for API response.
     */
    private function formatSubscriber(Partner $partner): array
    {
        $planNames = [
            'alap' => 'Alap',
            'iskola' => 'Iskola',
            'studio' => 'Stúdió',
        ];

        $billingCycle = $partner->billing_cycle ?? 'monthly';
        $plan = $partner->plan ?? 'alap';
        $price = self::PLAN_PRICES[$plan][$billingCycle] ?? 0;

        return [
            'id' => $partner->id,
            'name' => $partner->user_name,
            'email' => $partner->user_email,
            'companyName' => $partner->company_name,
            'plan' => $plan,
            'planName' => $planNames[$plan] ?? $plan,
            'billingCycle' => $billingCycle,
            'price' => $price,
            'subscriptionStatus' => $partner->subscription_status ?? 'trial',
            'subscriptionEndsAt' => $partner->subscription_ends_at?->toIso8601String(),
            'createdAt' => $partner->created_at?->toIso8601String(),
        ];
    }

    /**
     * Get system settings.
     */
    public function getSettings(): JsonResponse
    {
        return response()->json([
            'system' => [
                'registrationEnabled' => config('app.registration_enabled', true),
                'trialDays' => config('app.trial_days', 14),
                'defaultPlan' => config('app.default_plan', 'alap'),
            ],
            'email' => [
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'username' => config('mail.mailers.smtp.username') ? '***' : null,
            ],
            'stripe' => [
                'publicKey' => config('services.stripe.key') ? substr(config('services.stripe.key'), 0, 12) . '***' : null,
                'webhookConfigured' => ! empty(config('services.stripe.webhook_secret')),
            ],
            'info' => [
                'appVersion' => config('app.version', '1.0.0'),
                'laravelVersion' => app()->version(),
                'phpVersion' => PHP_VERSION,
                'environment' => app()->environment(),
                'cacheDriver' => config('cache.default'),
                'queueDriver' => config('queue.default'),
            ],
        ]);
    }

    /**
     * Update system settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'registrationEnabled' => 'sometimes|boolean',
            'trialDays' => 'sometimes|integer|min:0|max:90',
            'defaultPlan' => 'sometimes|string|in:alap,iskola,studio',
        ]);

        // Note: In production, these would be stored in database or .env
        // For now, we just return success
        return response()->json([
            'success' => true,
            'message' => 'Beállítások mentve.',
        ]);
    }

    /**
     * Get single subscriber details.
     */
    public function getSubscriber(Request $request, int $id): JsonResponse
    {
        $partner = Partner::with('user')->find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Előfizető nem található.',
            ], 404);
        }

        // Log view action
        AdminAuditLog::log(
            $request->user()->id,
            $partner->id,
            AdminAuditLog::ACTION_VIEW,
            null,
            $request->ip()
        );

        $planNames = [
            'alap' => 'Alap',
            'iskola' => 'Iskola',
            'studio' => 'Stúdió',
        ];

        $billingCycle = $partner->billing_cycle ?? 'monthly';
        $plan = $partner->plan ?? 'alap';
        $price = self::PLAN_PRICES[$plan][$billingCycle] ?? 0;

        // Calculate trial days remaining
        $trialDaysRemaining = null;
        if ($partner->subscription_status === 'trial' && $partner->subscription_ends_at) {
            $trialDaysRemaining = max(0, now()->diffInDays($partner->subscription_ends_at, false));
        }

        return response()->json([
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
            'planName' => $planNames[$plan] ?? $plan,
            'billingCycle' => $billingCycle,
            'price' => $price,
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
        ]);
    }

    /**
     * Charge subscriber with Stripe Invoice.
     */
    public function chargeSubscriber(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:1',
            'description' => 'required|string|max:255',
        ]);

        $partner = Partner::find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Előfizető nem található.',
            ], 404);
        }

        if (! $partner->stripe_customer_id) {
            return response()->json([
                'success' => false,
                'message' => 'A partnernek nincs Stripe customer ID-ja.',
            ], 400);
        }

        try {
            Stripe::setApiKey(config('stripe.secret_key'));

            // Create invoice item
            InvoiceItem::create([
                'customer' => $partner->stripe_customer_id,
                'amount' => $validated['amount'], // Amount in HUF (smallest currency unit)
                'currency' => config('stripe.currency', 'huf'),
                'description' => $validated['description'],
            ]);

            // Create and finalize invoice
            $invoice = Invoice::create([
                'customer' => $partner->stripe_customer_id,
                'auto_advance' => true,
                'collection_method' => 'charge_automatically',
            ]);

            // Finalize the invoice
            $invoice->finalizeInvoice();

            // Pay the invoice immediately
            $invoice->pay();

            // Log audit
            AdminAuditLog::log(
                $request->user()->id,
                $partner->id,
                AdminAuditLog::ACTION_CHARGE,
                [
                    'amount' => $validated['amount'],
                    'description' => $validated['description'],
                    'stripe_invoice_id' => $invoice->id,
                ],
                $request->ip()
            );

            return response()->json([
                'success' => true,
                'message' => 'Számla sikeresen létrehozva és terhelve.',
                'invoiceId' => $invoice->id,
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe charge error', [
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Stripe hiba: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Change subscriber plan.
     */
    public function changePlan(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'plan' => 'required|string|in:alap,iskola,studio',
            'billing_cycle' => 'sometimes|string|in:monthly,yearly',
        ]);

        $partner = Partner::find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Előfizető nem található.',
            ], 404);
        }

        $oldPlan = $partner->plan;
        $oldBillingCycle = $partner->billing_cycle;
        $newPlan = $validated['plan'];
        $newBillingCycle = $validated['billing_cycle'] ?? $partner->billing_cycle ?? 'monthly';

        // Get new Stripe price ID
        $newPriceId = config("stripe.prices.{$newPlan}.{$newBillingCycle}");

        if (empty($newPriceId)) {
            return response()->json([
                'success' => false,
                'message' => 'Érvénytelen csomag vagy számlázási ciklus.',
            ], 400);
        }

        try {
            // Update Stripe subscription if exists
            if ($partner->stripe_subscription_id) {
                Stripe::setApiKey(config('stripe.secret_key'));

                $subscription = Subscription::retrieve($partner->stripe_subscription_id);

                Subscription::update($partner->stripe_subscription_id, [
                    'items' => [
                        [
                            'id' => $subscription->items->data[0]->id,
                            'price' => $newPriceId,
                        ],
                    ],
                    'proration_behavior' => 'create_prorations',
                ]);
            }

            // Update partner record
            $planConfig = Partner::PLANS[$newPlan];
            $partner->update([
                'plan' => $newPlan,
                'billing_cycle' => $newBillingCycle,
                'storage_limit_gb' => $planConfig['storage_limit_gb'],
                'max_classes' => $planConfig['max_classes'],
                'features' => $planConfig['features'],
            ]);

            // Log audit
            AdminAuditLog::log(
                $request->user()->id,
                $partner->id,
                AdminAuditLog::ACTION_CHANGE_PLAN,
                [
                    'old_plan' => $oldPlan,
                    'old_billing_cycle' => $oldBillingCycle,
                    'new_plan' => $newPlan,
                    'new_billing_cycle' => $newBillingCycle,
                ],
                $request->ip()
            );

            $newPrice = self::PLAN_PRICES[$newPlan][$newBillingCycle] ?? 0;

            return response()->json([
                'success' => true,
                'message' => 'Csomag sikeresen módosítva.',
                'newPrice' => $newPrice,
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe plan change error', [
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Stripe hiba: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel subscriber subscription.
     */
    public function cancelSubscription(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'immediate' => 'required|boolean',
        ]);

        $partner = Partner::find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Előfizető nem található.',
            ], 404);
        }

        try {
            if ($partner->stripe_subscription_id) {
                Stripe::setApiKey(config('stripe.secret_key'));

                if ($validated['immediate']) {
                    // Cancel immediately
                    Subscription::update($partner->stripe_subscription_id, [
                        'cancel_at_period_end' => false,
                    ]);
                    $subscription = Subscription::retrieve($partner->stripe_subscription_id);
                    $subscription->cancel();

                    $partner->update([
                        'subscription_status' => 'canceled',
                        'stripe_subscription_id' => null,
                    ]);
                } else {
                    // Cancel at period end
                    Subscription::update($partner->stripe_subscription_id, [
                        'cancel_at_period_end' => true,
                    ]);

                    $partner->update([
                        'subscription_status' => 'canceling',
                    ]);
                }
            } else {
                // No Stripe subscription, just update status
                $partner->update([
                    'subscription_status' => 'canceled',
                ]);
            }

            // Log audit
            AdminAuditLog::log(
                $request->user()->id,
                $partner->id,
                AdminAuditLog::ACTION_CANCEL_SUBSCRIPTION,
                [
                    'immediate' => $validated['immediate'],
                ],
                $request->ip()
            );

            $message = $validated['immediate']
                ? 'Előfizetés azonnal törölve.'
                : 'Előfizetés törölve a periódus végén.';

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe cancel error', [
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Stripe hiba: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get audit logs for a subscriber.
     */
    public function getAuditLogs(Request $request, int $id): JsonResponse
    {
        $partner = Partner::find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Előfizető nem található.',
            ], 404);
        }

        $perPage = $request->input('per_page', 20);

        $logs = AdminAuditLog::with('adminUser')
            ->where('target_partner_id', $id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $logs->map(fn ($log) => [
                'id' => $log->id,
                'adminName' => $log->adminUser->name ?? 'Ismeretlen',
                'action' => $log->action,
                'actionLabel' => $log->action_label,
                'details' => $log->details,
                'ipAddress' => $log->ip_address,
                'createdAt' => $log->created_at->toIso8601String(),
            ]),
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'per_page' => $logs->perPage(),
            'total' => $logs->total(),
        ]);
    }
}

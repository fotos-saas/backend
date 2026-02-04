<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Services\Subscription\PartnerRegistrationService;
use App\Services\Subscription\SubscriptionStripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Subscription Controller
 *
 * Partner előfizetés kezelés:
 * - Checkout és regisztráció
 * - Előfizetés állapot lekérdezés
 * - Subscription management (cancel, resume, pause, unpause)
 * - Számlák
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionStripeService $stripeService,
        private readonly PartnerRegistrationService $registrationService,
    ) {}

    /**
     * Create Stripe Checkout Session for partner registration
     */
    public function createCheckoutSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'billing.company_name' => ['required', 'string', 'max:255'],
            'billing.tax_number' => ['nullable', 'string', 'max:50'],
            'billing.country' => ['required', 'string', 'max:100'],
            'billing.postal_code' => ['required', 'string', 'max:10'],
            'billing.city' => ['required', 'string', 'max:100'],
            'billing.address' => ['required', 'string', 'max:255'],
            'billing.phone' => ['required', 'string', 'max:50'],
            'plan' => ['required', 'string', 'in:alap,iskola,studio'],
            'billing_cycle' => ['required', 'string', 'in:monthly,yearly'],
            'is_desktop' => ['nullable', 'boolean'],
        ]);

        try {
            $registrationToken = $this->registrationService->prepareRegistration($validated);

            $session = $this->stripeService->createCheckoutSession([
                'email' => $validated['email'],
                'plan' => $validated['plan'],
                'billing_cycle' => $validated['billing_cycle'],
                'is_desktop' => $validated['is_desktop'] ?? false,
            ], $registrationToken);

            Log::info('Stripe Checkout Session created for subscription', [
                'session_id' => $session->id,
                'email' => $validated['email'],
                'plan' => $validated['plan'],
            ]);

            return response()->json([
                'checkout_url' => $session->url,
                'session_id' => $session->id,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe Checkout Session', [
                'error' => $e->getMessage(),
                'email' => $validated['email'],
            ]);

            return response()->json([
                'message' => 'Hiba történt a fizetési munkamenet létrehozásakor.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Complete registration after successful subscription payment
     */
    public function completeRegistration(Request $request): JsonResponse
    {
        $request->validate(['session_id' => ['required', 'string']]);

        try {
            $session = $this->stripeService->retrieveCheckoutSession($request->input('session_id'));

            if ($session->status !== 'complete') {
                return response()->json(['message' => 'A fizetés még nem fejeződött be.'], 400);
            }

            $registrationToken = $session->metadata->registration_token ?? null;
            if (! $registrationToken) {
                return response()->json(['message' => 'Érvénytelen munkamenet.'], 400);
            }

            $registrationData = $this->registrationService->getRegistrationData($registrationToken);
            if (! $registrationData) {
                return response()->json([
                    'message' => 'A regisztrációs adatok lejártak. Kérjük, kezdd újra a regisztrációt.',
                ], 400);
            }

            // Double-submit protection
            if ($this->registrationService->isEmailRegistered($registrationData['email'])) {
                $this->registrationService->clearRegistrationCache($registrationToken);

                return response()->json([
                    'message' => 'A regisztráció már megtörtént. Jelentkezz be!',
                    'already_registered' => true,
                ]);
            }

            $customerId = is_string($session->customer) ? $session->customer : $session->customer->id;
            $subscriptionId = is_string($session->subscription) ? $session->subscription : $session->subscription->id;

            $result = $this->registrationService->createPartnerWithUser(
                $registrationData,
                $customerId,
                $subscriptionId
            );

            $this->registrationService->clearRegistrationCache($registrationToken);

            Log::info('Partner registration completed', [
                'user_id' => $result['user']->id,
                'partner_id' => $result['partner']->id,
                'plan' => $registrationData['plan'],
            ]);

            return response()->json([
                'message' => 'Sikeres regisztráció! Most már bejelentkezhetsz.',
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to complete registration', [
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Hiba történt a regisztráció véglegesítésekor.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get subscription details for the current partner
     */
    public function getSubscription(Request $request): JsonResponse
    {
        $partner = $this->getPartnerWithAddons($request->user()->id);

        if (! $partner) {
            return response()->json(['message' => 'Partner profil nem található.'], 404);
        }

        $response = $this->buildSubscriptionResponse($partner);

        // Stripe details (cached)
        if ($partner->stripe_subscription_id) {
            try {
                $stripeData = $this->stripeService->getSubscriptionDetails($partner->stripe_subscription_id);
                $response = array_merge($response, $stripeData);
            } catch (\Exception $e) {
                $this->stripeService->clearSubscriptionCache($partner->stripe_subscription_id);
                Log::warning('Failed to retrieve Stripe subscription', [
                    'subscription_id' => $partner->stripe_subscription_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json($response);
    }

    /**
     * Create Stripe Customer Portal session
     */
    public function createPortalSession(Request $request): JsonResponse
    {
        $partner = $this->getPartner($request->user()->id);

        if (! $partner?->stripe_customer_id) {
            return response()->json(['message' => 'Nincs aktív előfizetésed.'], 400);
        }

        try {
            $portalSession = $this->stripeService->createPortalSession(
                $partner->stripe_customer_id,
                config('stripe.success_url')
            );

            return response()->json(['portal_url' => $portalSession->url]);
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe Portal session', [
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Hiba történt a fiókkezelő megnyitásakor.'], 500);
        }
    }

    /**
     * Cancel subscription (at period end)
     */
    public function cancelSubscription(Request $request): JsonResponse
    {
        $partner = $this->getPartner($request->user()->id);

        if (! $partner?->stripe_subscription_id) {
            return response()->json(['message' => 'Nincs aktív előfizetésed.'], 400);
        }

        try {
            $subscription = $this->stripeService->cancelAtPeriodEnd($partner->stripe_subscription_id);
            $partner->update(['subscription_status' => 'canceling']);

            Log::info('Subscription cancellation scheduled', [
                'partner_id' => $partner->id,
                'cancel_at' => date('Y-m-d H:i:s', $subscription->current_period_end),
            ]);

            return response()->json([
                'message' => 'Az előfizetésed le lesz mondva a jelenlegi időszak végén.',
                'cancel_at' => date('Y-m-d H:i:s', $subscription->current_period_end),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription', ['partner_id' => $partner->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Hiba történt az előfizetés lemondásakor.'], 500);
        }
    }

    /**
     * Resume a canceled subscription
     */
    public function resumeSubscription(Request $request): JsonResponse
    {
        $partner = $this->getPartner($request->user()->id);

        if (! $partner?->stripe_subscription_id) {
            return response()->json(['message' => 'Nincs aktív előfizetésed.'], 400);
        }

        try {
            $this->stripeService->resumeSubscription($partner->stripe_subscription_id);
            $partner->update(['subscription_status' => 'active']);

            Log::info('Subscription resumed', ['partner_id' => $partner->id]);

            return response()->json(['message' => 'Az előfizetésed újra aktív!']);
        } catch (\Exception $e) {
            Log::error('Failed to resume subscription', ['partner_id' => $partner->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Hiba történt az előfizetés újraaktiválásakor.'], 500);
        }
    }

    /**
     * Pause subscription
     */
    public function pauseSubscription(Request $request): JsonResponse
    {
        $partner = $this->getPartner($request->user()->id);

        if (! $partner?->stripe_subscription_id) {
            return response()->json(['message' => 'Nincs aktív előfizetésed.'], 400);
        }

        if ($partner->subscription_status === 'paused') {
            return response()->json(['message' => 'Az előfizetésed már szüneteltetve van.'], 400);
        }

        try {
            $this->stripeService->pauseSubscription($partner);
            $partner->update(['subscription_status' => 'paused', 'paused_at' => now()]);

            $pausedPrice = config("plans.plans.{$partner->plan}.paused_price");

            Log::info('Subscription paused', ['partner_id' => $partner->id, 'paused_price' => $pausedPrice]);

            return response()->json([
                'message' => 'Az előfizetésed szüneteltetve lett.',
                'paused_price' => $pausedPrice,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Log::error('Failed to pause subscription', ['partner_id' => $partner->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Hiba történt az előfizetés szüneteltetésekor.'], 500);
        }
    }

    /**
     * Resume a paused subscription
     */
    public function unpauseSubscription(Request $request): JsonResponse
    {
        $partner = $this->getPartner($request->user()->id);

        if (! $partner?->stripe_subscription_id) {
            return response()->json(['message' => 'Nincs aktív előfizetésed.'], 400);
        }

        if ($partner->subscription_status !== 'paused') {
            return response()->json(['message' => 'Az előfizetésed nincs szüneteltetve.'], 400);
        }

        try {
            $this->stripeService->unpauseSubscription($partner);
            $partner->update(['subscription_status' => 'active', 'paused_at' => null]);

            Log::info('Subscription unpaused', ['partner_id' => $partner->id]);

            return response()->json(['message' => 'Az előfizetésed újra aktív!']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Log::error('Failed to unpause subscription', ['partner_id' => $partner->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Hiba történt az előfizetés újraaktiválásakor.'], 500);
        }
    }

    /**
     * Get invoices from Stripe
     */
    public function getInvoices(Request $request): JsonResponse
    {
        $partner = $this->getPartner($request->user()->id);

        if (! $partner?->stripe_customer_id) {
            return response()->json(['invoices' => [], 'has_more' => false]);
        }

        try {
            $params = ['limit' => min((int) $request->input('per_page', 20), 100)];

            if ($startingAfter = $request->input('starting_after')) {
                $params['starting_after'] = $startingAfter;
            }

            if ($status = $request->input('status')) {
                $params['status'] = $status;
            }

            $stripeInvoices = $this->stripeService->getInvoices($partner->stripe_customer_id, $params);

            $invoices = collect($stripeInvoices->data)->map(fn ($inv) => [
                'id' => $inv->id,
                'number' => $inv->number,
                'amount' => $inv->amount_paid,
                'currency' => strtoupper($inv->currency),
                'status' => $inv->status,
                'created_at' => date('Y-m-d H:i:s', $inv->created),
                'pdf_url' => $inv->invoice_pdf,
                'hosted_url' => $inv->hosted_invoice_url,
            ]);

            return response()->json(['invoices' => $invoices, 'has_more' => $stripeInvoices->has_more]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve Stripe invoices', ['partner_id' => $partner->id, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Hiba történt a számlák lekérésekor.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Verify checkout session status
     */
    public function verifySession(Request $request): JsonResponse
    {
        $request->validate(['session_id' => ['required', 'string']]);

        try {
            return response()->json(
                $this->stripeService->verifySession($request->input('session_id'))
            );
        } catch (\Exception $e) {
            return response()->json(['message' => 'Érvénytelen munkamenet.'], 400);
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function getPartner(int $userId): ?Partner
    {
        return Partner::where('user_id', $userId)->first();
    }

    private function getPartnerWithAddons(int $userId): ?Partner
    {
        // Először próbáljuk tulajdonosként
        $partner = Partner::with(['addons' => fn ($q) => $q->where('status', 'active')])
            ->where('user_id', $userId)
            ->first();

        if ($partner) {
            return $partner;
        }

        // Ha nem tulajdonos, csapattagként keresünk
        // A user-ből vesszük a tablo_partner_id-t, majd a TabloPartner email alapján a Partner-t
        $user = \App\Models\User::find($userId);
        if ($user && $user->tablo_partner_id) {
            $tabloPartner = \App\Models\TabloPartner::find($user->tablo_partner_id);
            if ($tabloPartner && $tabloPartner->email) {
                $ownerUser = \App\Models\User::where('email', $tabloPartner->email)->first();
                if ($ownerUser) {
                    return Partner::with(['addons' => fn ($q) => $q->where('status', 'active')])
                        ->where('user_id', $ownerUser->id)
                        ->first();
                }
            }
        }

        return null;
    }

    private function buildSubscriptionResponse(Partner $partner): array
    {
        $planConfig = config("plans.plans.{$partner->plan}");
        $storageAddonConfig = config('plans.storage_addon');
        $addonsConfig = config('plans.addons');

        // Usage stats
        $usage = $this->getUsageStats($partner->user->tablo_partner_id ?? null);

        // Addon calculations
        $hasExtraStorage = ($partner->extra_storage_gb ?? 0) > 0;
        $activeAddons = $partner->addons;
        $hasAddons = $activeAddons->count() > 0;

        $addonPrices = [];
        foreach ($activeAddons as $addon) {
            $addonConfig = $addonsConfig[$addon->addon_key] ?? null;
            if ($addonConfig) {
                $addonPrices[$addon->addon_key] = [
                    'monthly' => $addonConfig['monthly_price'] ?? 0,
                    'yearly' => $addonConfig['yearly_price'] ?? 0,
                ];
            }
        }

        return [
            'partner_name' => $partner->company_name,
            'plan' => $partner->plan,
            'plan_name' => $planConfig['name'] ?? $partner->plan,
            'billing_cycle' => $partner->billing_cycle,
            'status' => $partner->subscription_status,
            'started_at' => $partner->subscription_started_at,
            'ends_at' => $partner->subscription_ends_at,
            'features' => $planConfig['feature_labels'] ?? [],
            'limits' => $planConfig['limits'] ?? [],
            'usage' => $usage,
            'is_modified' => $hasExtraStorage || $hasAddons,
            'has_extra_storage' => $hasExtraStorage,
            'extra_storage_gb' => $partner->extra_storage_gb ?? 0,
            'has_addons' => $hasAddons,
            'active_addons' => $activeAddons->pluck('addon_key')->toArray(),
            'prices' => [
                'plan_monthly' => $planConfig['monthly_price'] ?? 0,
                'plan_yearly' => $planConfig['yearly_price'] ?? 0,
                'storage_monthly' => $storageAddonConfig['unit_price_monthly'] ?? 150,
                'storage_yearly' => $storageAddonConfig['unit_price_yearly'] ?? 1620,
                'addons' => $addonPrices,
            ],
        ];
    }

    private function getUsageStats(?int $tabloPartnerId): array
    {
        if (! $tabloPartnerId) {
            return ['schools' => 0, 'classes' => 0, 'templates' => 0];
        }

        return [
            'schools' => \App\Models\TabloSchool::whereHas('projects', fn ($q) => $q->where('partner_id', $tabloPartnerId))->count(),
            'classes' => \App\Models\TabloProject::where('partner_id', $tabloPartnerId)->count(),
            'templates' => 0,
        ];
    }
}

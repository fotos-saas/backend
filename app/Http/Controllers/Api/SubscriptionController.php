<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Checkout\Session;
use Stripe\Invoice;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Customer;

class SubscriptionController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('stripe.secret_key'));
    }

    /**
     * Create Stripe Checkout Session for partner registration (subscription)
     */
    public function createCheckoutSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Account
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            // Billing
            'billing.company_name' => ['required', 'string', 'max:255'],
            'billing.tax_number' => ['nullable', 'string', 'max:50'],
            'billing.country' => ['required', 'string', 'max:100'],
            'billing.postal_code' => ['required', 'string', 'max:10'],
            'billing.city' => ['required', 'string', 'max:100'],
            'billing.address' => ['required', 'string', 'max:255'],
            'billing.phone' => ['required', 'string', 'max:50'],
            // Plan
            'plan' => ['required', 'string', 'in:alap,iskola,studio'],
            'billing_cycle' => ['required', 'string', 'in:monthly,yearly'],
        ]);

        $plan = $validated['plan'];
        $billingCycle = $validated['billing_cycle'];

        // Get the Stripe Price ID from config
        $priceId = config("stripe.prices.{$plan}.{$billingCycle}");

        if (empty($priceId)) {
            Log::error('Stripe Price ID not configured', [
                'plan' => $plan,
                'billing_cycle' => $billingCycle,
            ]);

            return response()->json([
                'message' => 'A kiválasztott csomag jelenleg nem elérhető.',
            ], 400);
        }

        try {
            // Store pending registration data in cache
            $registrationToken = Str::uuid()->toString();

            // SECURITY: A jelszót hash-elve tároljuk a cache-ben is
            $registrationData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'billing' => $validated['billing'],
                'plan' => $plan,
                'billing_cycle' => $billingCycle,
                'created_at' => now()->toIso8601String(),
            ];

            // Store in cache for 1 hour
            cache()->put(
                "registration:{$registrationToken}",
                $registrationData,
                now()->addHour()
            );

            $planConfig = config("plans.plans.{$plan}");

            // Create Stripe Checkout Session for SUBSCRIPTION
            $session = Session::create([
                'payment_method_types' => ['card'],
                'mode' => 'subscription',
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'success_url' => config('stripe.success_url') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('stripe.cancel_url'),
                'customer_email' => $validated['email'],
                'metadata' => [
                    'registration_token' => $registrationToken,
                    'plan' => $plan,
                    'billing_cycle' => $billingCycle,
                ],
                'subscription_data' => [
                    'trial_period_days' => 14,
                    'metadata' => [
                        'registration_token' => $registrationToken,
                        'plan' => $plan,
                        'billing_cycle' => $billingCycle,
                    ],
                ],
                'locale' => 'hu',
                'allow_promotion_codes' => true,
                'billing_address_collection' => 'auto',
                'tax_id_collection' => [
                    'enabled' => true,
                ],
            ]);

            Log::info('Stripe Checkout Session created for subscription', [
                'session_id' => $session->id,
                'email' => $validated['email'],
                'plan' => $plan,
                'billing_cycle' => $billingCycle,
                'price_id' => $priceId,
            ]);

            return response()->json([
                'checkout_url' => $session->url,
                'session_id' => $session->id,
            ]);

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
        $request->validate([
            'session_id' => ['required', 'string'],
        ]);

        $sessionId = $request->input('session_id');

        try {
            $session = Session::retrieve([
                'id' => $sessionId,
                'expand' => ['subscription', 'customer'],
            ]);

            // Check if subscription is active
            if ($session->status !== 'complete') {
                return response()->json([
                    'message' => 'A fizetés még nem fejeződött be.',
                ], 400);
            }

            $registrationToken = $session->metadata->registration_token ?? null;

            if (!$registrationToken) {
                return response()->json([
                    'message' => 'Érvénytelen munkamenet.',
                ], 400);
            }

            // Get registration data from cache
            $registrationData = cache()->get("registration:{$registrationToken}");

            if (!$registrationData) {
                return response()->json([
                    'message' => 'A regisztrációs adatok lejártak. Kérjük, kezdd újra a regisztrációt.',
                ], 400);
            }

            // Check if user already exists (double-submit protection)
            if (User::where('email', $registrationData['email'])->exists()) {
                cache()->forget("registration:{$registrationToken}");

                return response()->json([
                    'message' => 'A regisztráció már megtörtént. Jelentkezz be!',
                    'already_registered' => true,
                ]);
            }

            // Get subscription details
            $subscription = $session->subscription;
            $customerId = $session->customer;

            // Calculate subscription end date based on billing cycle
            $subscriptionEndsAt = $registrationData['billing_cycle'] === 'yearly'
                ? now()->addYear()
                : now()->addMonth();

            // Create user and partner in transaction
            DB::beginTransaction();
            try {
                // Create user (a jelszó már hash-elve van a cache-ben)
                $user = User::create([
                    'name' => $registrationData['name'],
                    'email' => $registrationData['email'],
                    'password' => $registrationData['password'],
                    'phone' => $registrationData['billing']['phone'] ?? null,
                    'email_verified_at' => now(),
                    'password_set' => true,
                ]);

                // Assign partner role
                $user->assignRole('partner');

                // Create partner profile with subscription info
                $partner = Partner::create([
                    'user_id' => $user->id,
                    'company_name' => $registrationData['billing']['company_name'],
                    'tax_number' => $registrationData['billing']['tax_number'],
                    'billing_country' => $registrationData['billing']['country'],
                    'billing_postal_code' => $registrationData['billing']['postal_code'],
                    'billing_city' => $registrationData['billing']['city'],
                    'billing_address' => $registrationData['billing']['address'],
                    'phone' => $registrationData['billing']['phone'],
                    'plan' => $registrationData['plan'],
                    'billing_cycle' => $registrationData['billing_cycle'],
                    'stripe_customer_id' => is_string($customerId) ? $customerId : $customerId->id,
                    'stripe_subscription_id' => is_string($subscription) ? $subscription : $subscription->id,
                    'subscription_status' => 'active',
                    'subscription_started_at' => now(),
                    'subscription_ends_at' => $subscriptionEndsAt,
                ]);

                DB::commit();

                // Clear registration cache
                cache()->forget("registration:{$registrationToken}");

                Log::info('Partner registration completed with subscription', [
                    'user_id' => $user->id,
                    'partner_id' => $partner->id,
                    'plan' => $registrationData['plan'],
                    'stripe_subscription_id' => $partner->stripe_subscription_id,
                ]);

                return response()->json([
                    'message' => 'Sikeres regisztráció! Most már bejelentkezhetsz.',
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Failed to complete registration', [
                'session_id' => $sessionId,
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
     *
     * OPTIMALIZÁCIÓ:
     * - Eager loading: Partner + activeAddons egy query-ben
     * - Stripe API cache: 5 perc TTL (csökkenti a Stripe API hívásokat)
     * - Usage stats: párhuzamos count query-k
     */
    public function getSubscription(Request $request): JsonResponse
    {
        $user = $request->user();

        // OPTIMALIZÁCIÓ: Eager loading - Partner + aktív addonok egy query-ben
        $partner = Partner::with(['addons' => function ($q) {
            $q->where('status', 'active');
        }])->where('user_id', $user->id)->first();

        if (!$partner) {
            return response()->json([
                'message' => 'Partner profil nem található.',
            ], 404);
        }

        $planConfig = config("plans.plans.{$partner->plan}");
        $storageAddonConfig = config('plans.storage_addon');
        $addonsConfig = config('plans.addons');

        // Usage statisztikák lekérése (párhuzamos query-k)
        $tabloPartnerId = $user->tablo_partner_id;
        $usage = [
            'schools' => 0,
            'classes' => 0,
            'templates' => 0,
        ];

        if ($tabloPartnerId) {
            // OPTIMALIZÁCIÓ: Egyetlen query JOIN-nal a schools count-hoz
            $usage['schools'] = \App\Models\TabloSchool::whereHas('projects', function ($q) use ($tabloPartnerId) {
                $q->where('partner_id', $tabloPartnerId);
            })->count();
            $usage['classes'] = \App\Models\TabloProject::where('partner_id', $tabloPartnerId)->count();
            $usage['templates'] = 0;
        }

        // Addonok már eager load-olva vannak
        $hasExtraStorage = ($partner->extra_storage_gb ?? 0) > 0;
        $activeAddons = $partner->addons; // Már betöltve!
        $hasAddons = $activeAddons->count() > 0;
        $isModified = $hasExtraStorage || $hasAddons;

        // Addon árak lekérése
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

        $response = [
            'plan' => $partner->plan,
            'plan_name' => $planConfig['name'] ?? $partner->plan,
            'billing_cycle' => $partner->billing_cycle,
            'status' => $partner->subscription_status,
            'started_at' => $partner->subscription_started_at,
            'ends_at' => $partner->subscription_ends_at,
            'features' => $planConfig['feature_labels'] ?? [],
            'limits' => $planConfig['limits'] ?? [],
            'usage' => $usage,
            // Módosítás jelzők
            'is_modified' => $isModified,
            'has_extra_storage' => $hasExtraStorage,
            'extra_storage_gb' => $partner->extra_storage_gb ?? 0,
            'has_addons' => $hasAddons,
            'active_addons' => $activeAddons->pluck('addon_key')->toArray(),
            // Árak (config-ból)
            'prices' => [
                'plan_monthly' => $planConfig['monthly_price'] ?? 0,
                'plan_yearly' => $planConfig['yearly_price'] ?? 0,
                'storage_monthly' => $storageAddonConfig['unit_price_monthly'] ?? 150,
                'storage_yearly' => $storageAddonConfig['unit_price_yearly'] ?? 1620,
                'addons' => $addonPrices,
            ],
        ];

        // Get more details from Stripe if subscription ID exists
        // OPTIMALIZÁCIÓ: Stripe API cache (5 perc TTL)
        if ($partner->stripe_subscription_id) {
            $cacheKey = "stripe_subscription:{$partner->stripe_subscription_id}";

            try {
                $stripeData = cache()->remember($cacheKey, now()->addMinutes(5), function () use ($partner) {
                    $subscription = Subscription::retrieve([
                        'id' => $partner->stripe_subscription_id,
                        'expand' => ['items.data.price'],
                    ]);

                    // Számoljuk ki a teljes havi költséget
                    $totalMonthlyAmount = 0;
                    foreach ($subscription->items->data as $item) {
                        $price = $item->price;
                        $amount = $price->unit_amount * $item->quantity;

                        // Ha éves, oszd 12-vel a havi egyenértékhez
                        if ($price->recurring && $price->recurring->interval === 'year') {
                            $amount = (int) round($amount / 12);
                        }

                        $totalMonthlyAmount += $amount;
                    }

                    return [
                        'stripe_status' => $subscription->status,
                        'current_period_end' => date('Y-m-d H:i:s', $subscription->current_period_end),
                        'cancel_at_period_end' => $subscription->cancel_at_period_end,
                        'monthly_cost' => (int) round($totalMonthlyAmount / 100),
                        'currency' => $subscription->currency,
                    ];
                });

                $response = array_merge($response, $stripeData);
            } catch (\Exception $e) {
                // Ha hiba van, töröljük a cache-t és logoljuk
                cache()->forget($cacheKey);

                Log::warning('Failed to retrieve Stripe subscription', [
                    'subscription_id' => $partner->stripe_subscription_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json($response);
    }

    /**
     * Create a Stripe Customer Portal session for managing subscription
     */
    public function createPortalSession(Request $request): JsonResponse
    {
        $user = $request->user();
        $partner = Partner::where('user_id', $user->id)->first();

        if (!$partner || !$partner->stripe_customer_id) {
            return response()->json([
                'message' => 'Nincs aktív előfizetésed.',
            ], 400);
        }

        try {
            $portalSession = \Stripe\BillingPortal\Session::create([
                'customer' => $partner->stripe_customer_id,
                'return_url' => config('stripe.success_url'),
            ]);

            return response()->json([
                'portal_url' => $portalSession->url,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create Stripe Portal session', [
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Hiba történt a fiókkezelő megnyitásakor.',
            ], 500);
        }
    }

    /**
     * Cancel subscription (at period end)
     */
    public function cancelSubscription(Request $request): JsonResponse
    {
        $user = $request->user();
        $partner = Partner::where('user_id', $user->id)->first();

        if (!$partner || !$partner->stripe_subscription_id) {
            return response()->json([
                'message' => 'Nincs aktív előfizetésed.',
            ], 400);
        }

        try {
            // Cancel at period end (not immediately)
            $subscription = Subscription::update($partner->stripe_subscription_id, [
                'cancel_at_period_end' => true,
            ]);

            $partner->update([
                'subscription_status' => 'canceling',
            ]);

            Log::info('Subscription cancellation scheduled', [
                'partner_id' => $partner->id,
                'subscription_id' => $partner->stripe_subscription_id,
                'cancel_at' => date('Y-m-d H:i:s', $subscription->current_period_end),
            ]);

            return response()->json([
                'message' => 'Az előfizetésed le lesz mondva a jelenlegi időszak végén.',
                'cancel_at' => date('Y-m-d H:i:s', $subscription->current_period_end),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription', [
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Hiba történt az előfizetés lemondásakor.',
            ], 500);
        }
    }

    /**
     * Resume a canceled subscription (before period ends)
     */
    public function resumeSubscription(Request $request): JsonResponse
    {
        $user = $request->user();
        $partner = Partner::where('user_id', $user->id)->first();

        if (!$partner || !$partner->stripe_subscription_id) {
            return response()->json([
                'message' => 'Nincs aktív előfizetésed.',
            ], 400);
        }

        try {
            $subscription = Subscription::update($partner->stripe_subscription_id, [
                'cancel_at_period_end' => false,
            ]);

            $partner->update([
                'subscription_status' => 'active',
            ]);

            Log::info('Subscription resumed', [
                'partner_id' => $partner->id,
                'subscription_id' => $partner->stripe_subscription_id,
            ]);

            return response()->json([
                'message' => 'Az előfizetésed újra aktív!',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to resume subscription', [
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Hiba történt az előfizetés újraaktiválásakor.',
            ], 500);
        }
    }

    /**
     * Pause subscription (switch to reduced paused price)
     */
    public function pauseSubscription(Request $request): JsonResponse
    {
        $user = $request->user();
        $partner = Partner::where('user_id', $user->id)->first();

        if (!$partner || !$partner->stripe_subscription_id) {
            return response()->json([
                'message' => 'Nincs aktív előfizetésed.',
            ], 400);
        }

        if ($partner->subscription_status === 'paused') {
            return response()->json([
                'message' => 'Az előfizetésed már szüneteltetve van.',
            ], 400);
        }

        // Get the paused price for the current plan
        $pausedPriceId = config("stripe.prices.{$partner->plan}.paused");

        if (empty($pausedPriceId)) {
            return response()->json([
                'message' => 'A szüneteltetés jelenleg nem elérhető ehhez a csomaghoz.',
            ], 400);
        }

        try {
            // Get current subscription to find the subscription item
            $subscription = Subscription::retrieve($partner->stripe_subscription_id);
            $subscriptionItemId = $subscription->items->data[0]->id;

            // Update subscription to paused price
            Subscription::update($partner->stripe_subscription_id, [
                'items' => [[
                    'id' => $subscriptionItemId,
                    'price' => $pausedPriceId,
                ]],
                'proration_behavior' => 'create_prorations',
            ]);

            $partner->update([
                'subscription_status' => 'paused',
                'paused_at' => now(),
            ]);

            $pausedPrice = config("plans.plans.{$partner->plan}.paused_price");

            Log::info('Subscription paused', [
                'partner_id' => $partner->id,
                'subscription_id' => $partner->stripe_subscription_id,
                'paused_price' => $pausedPrice,
            ]);

            return response()->json([
                'message' => 'Az előfizetésed szüneteltetve lett.',
                'paused_price' => $pausedPrice,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to pause subscription', [
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Hiba történt az előfizetés szüneteltetésekor.',
            ], 500);
        }
    }

    /**
     * Resume a paused subscription (switch back to original price)
     */
    public function unpauseSubscription(Request $request): JsonResponse
    {
        $user = $request->user();
        $partner = Partner::where('user_id', $user->id)->first();

        if (!$partner || !$partner->stripe_subscription_id) {
            return response()->json([
                'message' => 'Nincs aktív előfizetésed.',
            ], 400);
        }

        if ($partner->subscription_status !== 'paused') {
            return response()->json([
                'message' => 'Az előfizetésed nincs szüneteltetve.',
            ], 400);
        }

        // Get the original price based on billing cycle
        $originalPriceId = config("stripe.prices.{$partner->plan}.{$partner->billing_cycle}");

        if (empty($originalPriceId)) {
            return response()->json([
                'message' => 'Hiba történt az eredeti ár visszaállításakor.',
            ], 400);
        }

        try {
            // Get current subscription to find the subscription item
            $subscription = Subscription::retrieve($partner->stripe_subscription_id);
            $subscriptionItemId = $subscription->items->data[0]->id;

            // Update subscription back to original price
            Subscription::update($partner->stripe_subscription_id, [
                'items' => [[
                    'id' => $subscriptionItemId,
                    'price' => $originalPriceId,
                ]],
                'proration_behavior' => 'create_prorations',
            ]);

            $partner->update([
                'subscription_status' => 'active',
                'paused_at' => null,
            ]);

            Log::info('Subscription unpaused', [
                'partner_id' => $partner->id,
                'subscription_id' => $partner->stripe_subscription_id,
            ]);

            return response()->json([
                'message' => 'Az előfizetésed újra aktív!',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to unpause subscription', [
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Hiba történt az előfizetés újraaktiválásakor.',
            ], 500);
        }
    }

    /**
     * Get invoices from Stripe for the current partner
     */
    public function getInvoices(Request $request): JsonResponse
    {
        $user = $request->user();
        $partner = Partner::where('user_id', $user->id)->first();

        if (!$partner || !$partner->stripe_customer_id) {
            return response()->json([
                'invoices' => [],
                'has_more' => false,
            ]);
        }

        try {
            $params = [
                'customer' => $partner->stripe_customer_id,
                'limit' => min((int) $request->input('per_page', 20), 100),
            ];

            // Cursor-based pagination
            if ($startingAfter = $request->input('starting_after')) {
                $params['starting_after'] = $startingAfter;
            }

            // Status filter (paid, open, void, uncollectible)
            if ($status = $request->input('status')) {
                $params['status'] = $status;
            }

            $stripeInvoices = Invoice::all($params);

            $invoices = collect($stripeInvoices->data)->map(fn($inv) => [
                'id' => $inv->id,
                'number' => $inv->number,
                'amount' => $inv->amount_paid,
                'currency' => strtoupper($inv->currency),
                'status' => $inv->status,
                'created_at' => date('Y-m-d H:i:s', $inv->created),
                'pdf_url' => $inv->invoice_pdf,
                'hosted_url' => $inv->hosted_invoice_url,
            ]);

            return response()->json([
                'invoices' => $invoices,
                'has_more' => $stripeInvoices->has_more,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve Stripe invoices', [
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);

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
        $request->validate([
            'session_id' => ['required', 'string'],
        ]);

        try {
            $session = Session::retrieve($request->input('session_id'));

            return response()->json([
                'status' => $session->status,
                'payment_status' => $session->payment_status,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Érvénytelen munkamenet.',
            ], 400);
        }
    }
}

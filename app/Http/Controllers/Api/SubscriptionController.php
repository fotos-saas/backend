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
use Stripe\Stripe;

class SubscriptionController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('stripe.secret_key'));
    }

    /**
     * Create Stripe Checkout Session for partner registration
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

        // Plan prices (in HUF)
        $plans = [
            'alap' => [
                'name' => 'Alap csomag',
                'monthly' => 4990,
                'yearly' => 49900,
                'features' => ['20 GB tárhely', 'Max. 3 osztály', 'Email támogatás'],
            ],
            'iskola' => [
                'name' => 'Iskola csomag',
                'monthly' => 14990,
                'yearly' => 149900,
                'features' => ['100 GB tárhely', 'Max. 20 osztály', 'Prioritás támogatás'],
            ],
            'studio' => [
                'name' => 'Stúdió csomag',
                'monthly' => 29990,
                'yearly' => 299900,
                'features' => ['500 GB tárhely', 'Korlátlan osztály', 'Dedikált support'],
            ],
        ];

        $plan = $plans[$validated['plan']];
        $isYearly = $validated['billing_cycle'] === 'yearly';
        $price = $isYearly ? $plan['yearly'] : $plan['monthly'];

        try {
            // Store pending registration data in session/cache
            $registrationToken = Str::uuid()->toString();

            $registrationData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'], // Will be hashed when user is created
                'billing' => $validated['billing'],
                'plan' => $validated['plan'],
                'billing_cycle' => $validated['billing_cycle'],
                'created_at' => now()->toIso8601String(),
            ];

            // Store in cache for 1 hour
            cache()->put(
                "registration:{$registrationToken}",
                $registrationData,
                now()->addHour()
            );

            // Create Stripe Checkout Session
            $frontendUrl = config('app.frontend_url', 'https://app.tablostudio.hu');

            $sessionParams = [
                'payment_method_types' => ['card'],
                'mode' => $isYearly ? 'payment' : 'subscription',
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'huf',
                        'product_data' => [
                            'name' => $plan['name'],
                            'description' => implode(', ', $plan['features']),
                        ],
                        'unit_amount' => $price * 100, // Convert to fillér
                        'recurring' => $isYearly ? null : [
                            'interval' => 'month',
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'success_url' => $frontendUrl . '/register-success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $frontendUrl . '/register-app?cancelled=true',
                'customer_email' => $validated['email'],
                'metadata' => [
                    'registration_token' => $registrationToken,
                    'plan' => $validated['plan'],
                    'billing_cycle' => $validated['billing_cycle'],
                ],
                'locale' => 'hu',
            ];

            // For yearly, it's a one-time payment
            if ($isYearly) {
                unset($sessionParams['line_items'][0]['price_data']['recurring']);
            }

            $session = Session::create($sessionParams);

            Log::info('Stripe Checkout Session created for registration', [
                'session_id' => $session->id,
                'email' => $validated['email'],
                'plan' => $validated['plan'],
                'billing_cycle' => $validated['billing_cycle'],
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
     * Handle successful registration payment (called from webhook or success page)
     */
    public function handleSuccessfulPayment(string $sessionId): JsonResponse
    {
        try {
            $session = Session::retrieve($sessionId);

            if ($session->payment_status !== 'paid' && $session->status !== 'complete') {
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
                    'message' => 'Ez az email cím már regisztrálva van. Jelentkezz be!',
                    'already_registered' => true,
                ], 400);
            }

            // Create user and partner in transaction
            DB::beginTransaction();
            try {
                // Create user
                $user = User::create([
                    'name' => $registrationData['name'],
                    'email' => $registrationData['email'],
                    'password' => Hash::make($registrationData['password']),
                    'phone' => $registrationData['billing']['phone'] ?? null,
                    'email_verified_at' => now(), // Auto-verify since they paid
                    'password_set' => true,
                ]);

                // Assign partner role
                $user->assignRole('partner');

                // Create partner profile
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
                    'stripe_customer_id' => $session->customer,
                    'stripe_subscription_id' => $session->subscription,
                    'subscription_status' => 'active',
                    'subscription_started_at' => now(),
                    'subscription_ends_at' => $registrationData['billing_cycle'] === 'yearly'
                        ? now()->addYear()
                        : now()->addMonth(),
                ]);

                DB::commit();

                // Clear registration cache
                cache()->forget("registration:{$registrationToken}");

                Log::info('Partner registration completed', [
                    'user_id' => $user->id,
                    'partner_id' => $partner->id,
                    'plan' => $registrationData['plan'],
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
            Log::error('Failed to handle registration payment', [
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
     * Verify checkout session status (for frontend polling)
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Subscription\PartnerRegistrationService;
use App\Services\Subscription\SubscriptionStripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Subscription Checkout Controller
 *
 * Partner regisztráció és Stripe checkout kezelés:
 * - Checkout session létrehozás
 * - Regisztráció véglegesítés fizetés után
 * - Session ellenőrzés
 */
class SubscriptionCheckoutController extends Controller
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
}

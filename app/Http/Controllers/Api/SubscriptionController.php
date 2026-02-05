<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\TabloPartner;
use App\Models\User;
use App\Services\Subscription\SubscriptionResponseBuilder;
use App\Services\Subscription\SubscriptionStripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionStripeService $stripeService,
        private readonly SubscriptionResponseBuilder $responseBuilder,
    ) {}

    /**
     * Get subscription details for the current partner
     */
    public function getSubscription(Request $request): JsonResponse
    {
        $partner = $this->getPartnerWithAddons($request->user()->id);

        if (! $partner) {
            return response()->json(['message' => 'Partner profil nem található.'], 404);
        }

        $response = $this->responseBuilder->build($partner);

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

    private function getPartner(int $userId): ?Partner
    {
        return Partner::where('user_id', $userId)->first();
    }

    private function getPartnerWithAddons(int $userId): ?Partner
    {
        $partner = Partner::with(['addons' => fn ($q) => $q->where('status', 'active')])
            ->where('user_id', $userId)
            ->first();

        if ($partner) {
            return $partner;
        }

        $user = User::find($userId);
        if ($user && $user->tablo_partner_id) {
            $tabloPartner = TabloPartner::find($user->tablo_partner_id);
            if ($tabloPartner && $tabloPartner->email) {
                $ownerUser = User::where('email', $tabloPartner->email)->first();
                if ($ownerUser) {
                    return Partner::with(['addons' => fn ($q) => $q->where('status', 'active')])
                        ->where('user_id', $ownerUser->id)
                        ->first();
                }
            }
        }

        return null;
    }
}

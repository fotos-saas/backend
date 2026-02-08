<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\GuestBillingCharge;
use App\Models\TabloPartner;
use App\Services\PartnerStripeService;
use Illuminate\Support\Facades\Log;

class CreateCheckoutSessionAction
{
    public function __construct(
        private readonly PartnerStripeService $stripeService
    ) {}

    /**
     * Stripe Checkout session létrehozása egy pending charge-hoz.
     *
     * @return array{checkout_url: string, session_id: string}
     */
    public function execute(GuestBillingCharge $charge): array
    {
        if (! $charge->isPending()) {
            throw new \RuntimeException('Csak függőben lévő terhelésre lehet fizetést indítani.');
        }

        $project = $charge->project;
        $partner = TabloPartner::findOrFail($project->partner_id);

        if (! $partner->hasStripePaymentEnabled()) {
            throw new \RuntimeException('A partner Stripe fizetése nincs beállítva.');
        }

        $successUrl = config('billing.partner_checkout.success_url');
        $cancelUrl = config('billing.partner_checkout.cancel_url');

        Log::info('Creating partner checkout session', [
            'charge_id' => $charge->id,
            'partner_id' => $partner->id,
        ]);

        return $this->stripeService->createCheckoutSession(
            $charge,
            $partner,
            $successUrl,
            $cancelUrl
        );
    }
}

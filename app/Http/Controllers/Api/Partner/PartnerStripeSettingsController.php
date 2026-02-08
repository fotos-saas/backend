<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Requests\Api\Partner\UpdateStripeSettingsRequest;
use App\Models\TabloPartner;
use App\Services\PartnerStripeService;
use Illuminate\Http\JsonResponse;

class PartnerStripeSettingsController extends Controller
{
    use PartnerAuthTrait;

    public function show(): JsonResponse
    {
        $partner = TabloPartner::findOrFail($this->getPartnerIdOrFail());

        return $this->successResponse([
            'stripe_settings' => [
                'has_public_key' => $partner->payment_stripe_public_key !== null,
                'has_secret_key' => $partner->payment_stripe_secret_key !== null,
                'has_webhook_secret' => $partner->payment_stripe_webhook_secret !== null,
                'stripe_enabled' => $partner->payment_stripe_enabled,
                'webhook_url' => url("/api/partner-stripe/webhook/{$partner->id}"),
            ],
        ]);
    }

    public function update(UpdateStripeSettingsRequest $request): JsonResponse
    {
        $partner = TabloPartner::findOrFail($this->getPartnerIdOrFail());
        $data = $request->validated();

        if (array_key_exists('stripe_public_key', $data) || array_key_exists('stripe_secret_key', $data) || array_key_exists('stripe_webhook_secret', $data)) {
            $partner->setEncryptedStripeKeys(
                $data['stripe_public_key'] ?? $partner->getDecryptedStripePublicKey(),
                $data['stripe_secret_key'] ?? $partner->getDecryptedStripeSecretKey(),
                $data['stripe_webhook_secret'] ?? $partner->getDecryptedStripeWebhookSecret(),
            );
        }

        if (array_key_exists('stripe_enabled', $data)) {
            $partner->payment_stripe_enabled = $data['stripe_enabled'];
        }

        $partner->save();

        return $this->successResponse([
            'stripe_settings' => [
                'has_public_key' => $partner->payment_stripe_public_key !== null,
                'has_secret_key' => $partner->payment_stripe_secret_key !== null,
                'has_webhook_secret' => $partner->payment_stripe_webhook_secret !== null,
                'stripe_enabled' => $partner->payment_stripe_enabled,
                'webhook_url' => url("/api/partner-stripe/webhook/{$partner->id}"),
            ],
            'message' => 'Stripe beállítások mentve.',
        ]);
    }

    public function validateKeys(PartnerStripeService $stripeService): JsonResponse
    {
        $partner = TabloPartner::findOrFail($this->getPartnerIdOrFail());

        if (! $partner->payment_stripe_secret_key) {
            return $this->errorResponse('Nincs titkos kulcs megadva.', 422);
        }

        $valid = $stripeService->validateKeys($partner);

        return $this->successResponse([
            'valid' => $valid,
            'message' => $valid ? 'A Stripe kulcsok érvényesek.' : 'A Stripe kulcsok érvénytelenek.',
        ]);
    }
}

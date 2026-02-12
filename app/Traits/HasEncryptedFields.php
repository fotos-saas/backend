<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

/**
 * Titkositott mezok kezelese (Stripe kulcsok, API kulcsok).
 *
 * A TabloPartner model-hez tartozo encrypt/decrypt helper metodusok.
 */
trait HasEncryptedFields
{
    // ============ Invoice API Key ============

    public function getDecryptedApiKey(): ?string
    {
        if (! $this->invoice_api_key) {
            return null;
        }

        try {
            return Crypt::decryptString($this->invoice_api_key);
        } catch (\Exception) {
            return null;
        }
    }

    public function setEncryptedApiKey(?string $plainKey): void
    {
        $this->invoice_api_key = $plainKey ? Crypt::encryptString($plainKey) : null;
    }

    public function hasInvoicingEnabled(): bool
    {
        return $this->invoice_enabled && $this->invoice_api_key !== null;
    }

    // ============ Payment Stripe Keys ============

    public function getDecryptedStripePublicKey(): ?string
    {
        if (! $this->payment_stripe_public_key) {
            return null;
        }

        try {
            return Crypt::decryptString($this->payment_stripe_public_key);
        } catch (\Exception) {
            return null;
        }
    }

    public function getDecryptedStripeSecretKey(): ?string
    {
        if (! $this->payment_stripe_secret_key) {
            return null;
        }

        try {
            return Crypt::decryptString($this->payment_stripe_secret_key);
        } catch (\Exception) {
            return null;
        }
    }

    public function getDecryptedStripeWebhookSecret(): ?string
    {
        if (! $this->payment_stripe_webhook_secret) {
            return null;
        }

        try {
            return Crypt::decryptString($this->payment_stripe_webhook_secret);
        } catch (\Exception) {
            return null;
        }
    }

    public function setEncryptedStripeKeys(?string $publicKey, ?string $secretKey, ?string $webhookSecret): void
    {
        $this->payment_stripe_public_key = $publicKey ? Crypt::encryptString($publicKey) : null;
        $this->payment_stripe_secret_key = $secretKey ? Crypt::encryptString($secretKey) : null;
        $this->payment_stripe_webhook_secret = $webhookSecret ? Crypt::encryptString($webhookSecret) : null;
    }

    public function hasStripePaymentEnabled(): bool
    {
        return $this->payment_stripe_enabled
            && $this->payment_stripe_public_key !== null
            && $this->payment_stripe_secret_key !== null;
    }
}

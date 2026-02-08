<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Actions\Invoice\CreateInvoiceAction;
use App\Models\GuestBillingCharge;
use App\Models\TabloPartner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandlePartnerPaymentSuccessAction
{
    public function __construct(
        private readonly CreateInvoiceAction $createInvoiceAction
    ) {}

    /**
     * Sikeres fizetés feldolgozása: charge → paid + számla generálás.
     * DB tranzakció + lockForUpdate a race condition ellen (dupla webhook retry).
     */
    public function execute(GuestBillingCharge $charge, string $paymentIntentId): void
    {
        DB::transaction(function () use ($charge, $paymentIntentId) {
            $charge = GuestBillingCharge::lockForUpdate()->find($charge->id);

            if (!$charge || $charge->isPaid()) {
                Log::info('Charge already paid, skipping', ['charge_id' => $charge?->id]);
                return;
            }

            $charge->update([
                'status' => GuestBillingCharge::STATUS_PAID,
                'paid_at' => now(),
                'stripe_payment_intent_id' => $paymentIntentId,
            ]);

            Log::info('Partner charge marked as paid', [
                'charge_id' => $charge->id,
                'payment_intent' => $paymentIntentId,
            ]);

            // Számla generálás ha a partnernek van számlázási integráció
            $this->tryGenerateInvoice($charge);
        });
    }

    private function tryGenerateInvoice(GuestBillingCharge $charge): void
    {
        $project = $charge->project;
        $partner = TabloPartner::find($project->partner_id);

        if (! $partner || ! $partner->hasInvoicingEnabled()) {
            return;
        }

        try {
            $person = $charge->person;
            $customerName = $person?->name ?? 'Vendég';

            $result = $this->createInvoiceAction->execute($partner, [
                'type' => 'normal',
                'issue_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'fulfillment_date' => now()->toDateString(),
                'customer_name' => $customerName,
                'customer_email' => null,
                'tablo_project_id' => $project->id,
                'items' => [
                    [
                        'name' => $charge->service_label,
                        'quantity' => 1,
                        'unit_price' => $charge->amount_huf,
                        'unit' => 'db',
                        'description' => $charge->description ?? $charge->charge_number,
                    ],
                ],
                'sync_immediately' => true,
            ]);

            if ($result['success'] && $result['invoice']) {
                $invoice = $result['invoice'];
                $charge->update([
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_url' => $invoice->pdf_url ?? null,
                ]);

                Log::info('Invoice generated for partner charge', [
                    'charge_id' => $charge->id,
                    'invoice_number' => $invoice->invoice_number,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Auto invoice generation failed for partner charge', [
                'charge_id' => $charge->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Enums\InvoiceStatus;
use App\Models\TabloInvoice;
use App\Models\TabloPartner;
use App\Services\Invoice\InvoiceService;
use Illuminate\Support\Facades\DB;

class CreateInvoiceAction
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    /**
     * Számla + tételek létrehozása, összeg kalkuláció, opcionális API sync.
     *
     * @param  array{
     *   type: string,
     *   issue_date: string,
     *   due_date: string,
     *   fulfillment_date: string,
     *   customer_name: string,
     *   customer_email: ?string,
     *   customer_tax_number: ?string,
     *   customer_address: ?string,
     *   comment: ?string,
     *   internal_note: ?string,
     *   tablo_project_id: ?int,
     *   tablo_contact_id: ?int,
     *   items: array<int, array{name: string, quantity: float, unit_price: float, unit: string, description: ?string}>,
     *   sync_immediately: bool,
     * }  $data
     * @return array{success: bool, invoice: ?TabloInvoice, error: ?string}
     */
    public function execute(TabloPartner $partner, array $data): array
    {
        return DB::transaction(function () use ($partner, $data) {
            $vatPercentage = (float) ($partner->invoice_vat_percentage ?? 27.00);

            // Összeg kalkuláció a tételekből
            $totalNet = 0;
            $totalVat = 0;
            $totalGross = 0;

            $itemRows = [];
            foreach ($data['items'] as $item) {
                $qty = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $itemNet = round($qty * $unitPrice, 2);
                $itemVat = round($itemNet * ($vatPercentage / 100), 2);
                $itemGross = $itemNet + $itemVat;

                $totalNet += $itemNet;
                $totalVat += $itemVat;
                $totalGross += $itemGross;

                $itemRows[] = [
                    'name' => $item['name'],
                    'description' => $item['description'] ?? null,
                    'unit' => $item['unit'] ?? 'db',
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'net_amount' => $itemNet,
                    'vat_percentage' => $vatPercentage,
                    'vat_amount' => $itemVat,
                    'gross_amount' => $itemGross,
                ];
            }

            $invoiceNumber = $this->invoiceService->generateInvoiceNumber($partner);

            $invoice = TabloInvoice::create([
                'tablo_partner_id' => $partner->id,
                'tablo_project_id' => $data['tablo_project_id'] ?? null,
                'tablo_contact_id' => $data['tablo_contact_id'] ?? null,
                'provider' => $partner->invoice_provider,
                'invoice_number' => $invoiceNumber,
                'type' => $data['type'],
                'status' => InvoiceStatus::DRAFT,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'fulfillment_date' => $data['fulfillment_date'],
                'currency' => $partner->invoice_currency ?? 'HUF',
                'net_amount' => $totalNet,
                'vat_amount' => $totalVat,
                'gross_amount' => $totalGross,
                'vat_percentage' => $vatPercentage,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?? null,
                'customer_tax_number' => $data['customer_tax_number'] ?? null,
                'customer_address' => $data['customer_address'] ?? null,
                'comment' => $data['comment'] ?? $partner->invoice_comment,
                'internal_note' => $data['internal_note'] ?? null,
            ]);

            foreach ($itemRows as $row) {
                $invoice->items()->create($row);
            }

            // Azonnali szinkronizálás ha kérték és a partner-nek van API kulcsa
            if (! empty($data['sync_immediately']) && $partner->hasInvoicingEnabled()) {
                $invoice = $this->invoiceService->syncInvoice($invoice);
            }

            return ['success' => true, 'invoice' => $invoice->load('items'), 'error' => null];
        });
    }
}

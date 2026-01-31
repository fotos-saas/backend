<?php

namespace App\Services\Invoicing;

use App\Models\InvoicingProvider;
use App\Models\Order;
use App\Models\PartnerSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BillingoService implements InvoicingServiceInterface
{
    /**
     * Invoicing provider instance
     */
    public function __construct(
        private readonly InvoicingProvider $provider
    ) {}

    /**
     * Issue invoice for an order
     */
    public function issueInvoice(Order $order): array
    {
        $invoiceData = $this->mapOrderToInvoiceData($order);

        try {
            $response = $this->sendInvoiceRequest($invoiceData);

            Log::info('Billingo invoice issued', [
                'order_id' => $order->id,
                'invoice_number' => $response['invoice_number'] ?? null,
            ]);

            return [
                'success' => true,
                'invoice_number' => $response['invoice_number'],
                'invoice_id' => $response['invoice_id'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Billingo invoice failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get invoice PDF content
     */
    public function getInvoicePdf(string $invoiceId): string
    {
        $url = config('invoicing.providers.billingo.base_url').'/documents/'.$invoiceId.'/download';

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-API-KEY' => $this->provider->api_v3_key,
                ])
                ->get($url);

            if ($response->failed()) {
                throw new \Exception('Failed to download PDF from Billingo');
            }

            return $response->body();
        } catch (\Exception $e) {
            Log::error('Billingo PDF download failed', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Cancel an issued invoice
     */
    public function cancelInvoice(string $invoiceId): bool
    {
        // Billingo API cancellation logika
        Log::info('Billingo invoice cancellation requested', [
            'invoice_id' => $invoiceId,
        ]);

        return true;
    }

    /**
     * Map order data to Billingo invoice format
     */
    private function mapOrderToInvoiceData(Order $order): array
    {
        $partnerSetting = PartnerSetting::query()->where('is_active', true)->first();

        $invoiceData = [
            'fulfillment_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_method' => 'stripe',
            'language' => 'hu',
            'currency' => 'HUF',
            'comment' => 'Köszönjük a vásárlást!',
            'partner' => [
                'name' => $order->is_company_purchase
                    ? $order->company_name
                    : ($order->user?->name ?? $order->guest_name),
                'address' => [
                    'country_code' => 'HU',
                    'post_code' => '',
                    'city' => '',
                    'address' => $order->billing_address ?? $order->guest_address ?? '',
                ],
                'emails' => [$order->user?->email ?? $order->guest_email],
                'taxcode' => $order->tax_number ?? '',
            ],
            'items' => [],
        ];

        // Add order items
        foreach ($order->items as $item) {
            $netPrice = round($item->unit_price_huf / 1.27);
            $vatAmount = $item->unit_price_huf - $netPrice;

            $invoiceData['items'][] = [
                'name' => $item->size,
                'unit_price' => $item->unit_price_huf,
                'unit_price_type' => 'gross',
                'quantity' => $item->quantity,
                'unit' => 'db',
                'vat' => '27%',
                'net_unit_price' => $netPrice,
                'vat_amount' => $vatAmount * $item->quantity,
                'gross_amount' => $item->total_price_huf,
            ];
        }

        return $invoiceData;
    }

    /**
     * Send invoice request to Billingo API
     */
    private function sendInvoiceRequest(array $invoiceData): array
    {
        $url = config('invoicing.providers.billingo.base_url').'/documents';

        $response = Http::timeout(30)
            ->withHeaders([
                'X-API-KEY' => $this->provider->api_v3_key,
                'Content-Type' => 'application/json',
            ])
            ->post($url, $invoiceData);

        if ($response->failed()) {
            throw new \Exception('Billingo API request failed: '.$response->body());
        }

        $data = $response->json();

        return [
            'invoice_number' => $data['invoice_number'] ?? 'BI-'.time(),
            'invoice_id' => $data['id'] ?? null,
        ];
    }
}

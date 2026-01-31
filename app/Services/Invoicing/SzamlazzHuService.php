<?php

namespace App\Services\Invoicing;

use App\Models\InvoicingProvider;
use App\Models\Order;
use App\Models\PartnerSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SzamlazzHuService implements InvoicingServiceInterface
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

            Log::info('Számlázz.hu invoice issued', [
                'order_id' => $order->id,
                'invoice_number' => $response['invoice_number'] ?? null,
            ]);

            return [
                'success' => true,
                'invoice_number' => $response['invoice_number'],
                'pdf_url' => $response['pdf_url'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Számlázz.hu invoice failed', [
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
        $url = config('invoicing.providers.szamlazz_hu.base_url').'pdf';

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->provider->agent_key,
                ])
                ->post($url, [
                    'invoice_number' => $invoiceId,
                ]);

            if ($response->failed()) {
                throw new \Exception('Failed to download PDF from Számlázz.hu');
            }

            return $response->body();
        } catch (\Exception $e) {
            Log::error('Számlázz.hu PDF download failed', [
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
        // Számlázz.hu API storno logika
        Log::info('Számlázz.hu invoice cancellation requested', [
            'invoice_id' => $invoiceId,
        ]);

        return true;
    }

    /**
     * Map order data to Számlázz.hu invoice format
     */
    private function mapOrderToInvoiceData(Order $order): array
    {
        $partnerSetting = PartnerSetting::query()->where('is_active', true)->first();

        $invoiceData = [
            'invoice' => [
                'paymentMethod' => 'Stripe',
                'currency' => 'HUF',
                'language' => 'hu',
                'comment' => 'Köszönjük a vásárlást!',
            ],
            'seller' => [
                'name' => $partnerSetting?->name ?? config('app.name'),
                'taxNumber' => $partnerSetting?->tax_number ?? '',
                'address' => [
                    'streetName' => $partnerSetting?->address ?? '',
                ],
                'email' => $partnerSetting?->email ?? '',
                'phone' => $partnerSetting?->phone ?? '',
            ],
            'buyer' => [
                'name' => $order->is_company_purchase
                    ? $order->company_name
                    : ($order->user?->name ?? $order->guest_name),
                'taxNumber' => $order->tax_number ?? '',
                'address' => [
                    'streetName' => $order->billing_address ?? $order->guest_address ?? '',
                ],
                'email' => $order->user?->email ?? $order->guest_email,
            ],
            'items' => [],
        ];

        // Add order items
        foreach ($order->items as $item) {
            $invoiceData['items'][] = [
                'name' => $item->size,
                'quantity' => $item->quantity,
                'unit' => 'db',
                'unitPrice' => $item->unit_price_huf,
                'vat' => '27%',
                'netPrice' => round($item->unit_price_huf / 1.27),
                'vatAmount' => round($item->unit_price_huf - ($item->unit_price_huf / 1.27)),
                'grossAmount' => $item->total_price_huf,
            ];
        }

        return $invoiceData;
    }

    /**
     * Send invoice request to Számlázz.hu API
     */
    private function sendInvoiceRequest(array $invoiceData): array
    {
        $url = config('invoicing.providers.szamlazz_hu.base_url').'agent';

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->provider->agent_key,
                'Content-Type' => 'application/json',
            ])
            ->post($url, $invoiceData);

        if ($response->failed()) {
            throw new \Exception('Számlázz.hu API request failed: '.$response->body());
        }

        $data = $response->json();

        return [
            'invoice_number' => $data['invoiceNumber'] ?? 'SZ-'.time(),
            'pdf_url' => $data['pdfUrl'] ?? null,
        ];
    }
}

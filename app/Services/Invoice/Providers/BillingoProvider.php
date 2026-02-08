<?php

declare(strict_types=1);

namespace App\Services\Invoice\Providers;

use App\Contracts\InvoiceProviderInterface;
use App\Enums\InvoiceType;
use App\Models\TabloInvoice;
use App\Models\TabloPartner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BillingoProvider implements InvoiceProviderInterface
{
    private string $baseUrl;

    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('invoicing.providers.billingo.base_url');
        $this->timeout = config('invoicing.providers.billingo.timeout', 30);
    }

    public function createInvoice(TabloInvoice $invoice, TabloPartner $partner): array
    {
        $apiKey = $partner->getDecryptedApiKey();
        if (! $apiKey) {
            return ['success' => false, 'external_id' => null, 'invoice_number' => null, 'pdf_url' => null, 'error' => 'Hiányzó API kulcs'];
        }

        $payload = $this->buildInvoicePayload($invoice, $partner);

        try {
            $response = $this->apiRequest('POST', '/documents', $apiKey, $payload);

            if (isset($response['id'])) {
                return [
                    'success' => true,
                    'external_id' => (string) $response['id'],
                    'invoice_number' => $response['invoice_number'] ?? null,
                    'pdf_url' => null,
                    'error' => null,
                ];
            }

            return ['success' => false, 'external_id' => null, 'invoice_number' => null, 'pdf_url' => null, 'error' => $response['message'] ?? 'Ismeretlen hiba'];
        } catch (\Exception $e) {
            Log::error('Billingo számla létrehozás hiba', ['error' => $e->getMessage()]);

            return ['success' => false, 'external_id' => null, 'invoice_number' => null, 'pdf_url' => null, 'error' => 'Kapcsolódási hiba a Billingo-hoz'];
        }
    }

    public function cancelInvoice(TabloInvoice $invoice, TabloPartner $partner): array
    {
        $apiKey = $partner->getDecryptedApiKey();
        if (! $apiKey || ! $invoice->external_id) {
            return ['success' => false, 'external_id' => null, 'error' => 'Hiányzó API kulcs vagy külső azonosító'];
        }

        try {
            $response = $this->apiRequest('POST', "/documents/{$invoice->external_id}/cancel", $apiKey);

            if (isset($response['id'])) {
                return ['success' => true, 'external_id' => (string) $response['id'], 'error' => null];
            }

            return ['success' => false, 'external_id' => null, 'error' => $response['message'] ?? 'Sztornó hiba'];
        } catch (\Exception $e) {
            Log::error('Billingo sztornó hiba', ['error' => $e->getMessage()]);

            return ['success' => false, 'external_id' => null, 'error' => 'Kapcsolódási hiba'];
        }
    }

    public function downloadPdf(TabloInvoice $invoice, TabloPartner $partner): array
    {
        $apiKey = $partner->getDecryptedApiKey();
        if (! $apiKey || ! $invoice->external_id) {
            return ['success' => false, 'content' => null, 'error' => 'Hiányzó API kulcs vagy külső azonosító'];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['X-API-KEY' => $apiKey])
                ->get("{$this->baseUrl}/documents/{$invoice->external_id}/download");

            if ($response->successful()) {
                return ['success' => true, 'content' => $response->body(), 'error' => null];
            }

            return ['success' => false, 'content' => null, 'error' => 'PDF letöltés hiba'];
        } catch (\Exception $e) {
            Log::error('Billingo PDF letöltés hiba', ['error' => $e->getMessage()]);

            return ['success' => false, 'content' => null, 'error' => 'Kapcsolódási hiba'];
        }
    }

    public function validateCredentials(TabloPartner $partner): bool
    {
        $apiKey = $partner->getDecryptedApiKey();
        if (! $apiKey) {
            return false;
        }

        try {
            $response = $this->apiRequest('GET', '/bank-accounts', $apiKey);

            return isset($response['data']) || is_array($response);
        } catch (\Exception) {
            return false;
        }
    }

    public function getName(): string
    {
        return 'Billingo';
    }

    private function buildInvoicePayload(TabloInvoice $invoice, TabloPartner $partner): array
    {
        $items = $invoice->items->map(fn ($item) => [
            'name' => $item->name,
            'unit_price' => (float) $item->unit_price,
            'unit_price_type' => 'net',
            'quantity' => (float) $item->quantity,
            'unit' => $item->unit,
            'vat' => $this->mapVatKey($item->vat_percentage),
            'comment' => $item->description,
        ])->toArray();

        return [
            'partner_id' => null,
            'block_id' => $partner->billingo_block_id ? (int) $partner->billingo_block_id : null,
            'bank_account_id' => $partner->billingo_bank_account_id ? (int) $partner->billingo_bank_account_id : null,
            'type' => $this->mapInvoiceType($invoice->type),
            'fulfillment_date' => $invoice->fulfillment_date->format('Y-m-d'),
            'due_date' => $invoice->due_date->format('Y-m-d'),
            'payment_method' => 'wire_transfer',
            'language' => $partner->invoice_language ?? 'hu',
            'currency' => $invoice->currency,
            'comment' => $invoice->comment,
            'items' => $items,
            'partner_name' => $invoice->customer_name,
            'partner_email' => $invoice->customer_email,
            'partner_taxcode' => $invoice->customer_tax_number,
            'partner_address' => [
                'address' => $invoice->customer_address,
            ],
        ];
    }

    private function mapInvoiceType(InvoiceType $type): string
    {
        return match ($type) {
            InvoiceType::INVOICE => 'invoice',
            InvoiceType::PROFORMA => 'proforma',
            InvoiceType::DEPOSIT => 'advance',
            InvoiceType::CANCELLATION => 'cancellation',
        };
    }

    private function mapVatKey(string|float $percentage): string
    {
        $pct = (float) $percentage;

        return match (true) {
            $pct === 27.0 => '27%',
            $pct === 18.0 => '18%',
            $pct === 5.0 => '5%',
            $pct === 0.0 => '0%',
            default => "{$pct}%",
        };
    }

    private function apiRequest(string $method, string $endpoint, string $apiKey, ?array $data = null): array
    {
        $request = Http::timeout($this->timeout)
            ->withHeaders(['X-API-KEY' => $apiKey])
            ->acceptJson();

        $url = $this->baseUrl.$endpoint;

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url),
            'POST' => $request->post($url, $data ?? []),
            'PUT' => $request->put($url, $data ?? []),
            'DELETE' => $request->delete($url),
            default => throw new \InvalidArgumentException("Ismeretlen HTTP metódus: {$method}"),
        };

        return $response->json() ?? [];
    }
}

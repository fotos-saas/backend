<?php

declare(strict_types=1);

namespace App\Services\Invoice\Providers;

use App\Contracts\InvoiceProviderInterface;
use App\Enums\InvoiceType;
use App\Models\TabloInvoice;
use App\Models\TabloPartner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SzamlazzProvider implements InvoiceProviderInterface
{
    private string $baseUrl;

    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('invoicing.providers.szamlazz_hu.base_url');
        $this->timeout = config('invoicing.providers.szamlazz_hu.timeout', 30);
    }

    public function createInvoice(TabloInvoice $invoice, TabloPartner $partner): array
    {
        $apiKey = $partner->getDecryptedApiKey();
        if (! $apiKey) {
            return ['success' => false, 'external_id' => null, 'invoice_number' => null, 'pdf_url' => null, 'error' => 'Hiányzó API kulcs'];
        }

        $xml = $this->buildInvoiceXml($invoice, $partner, $apiKey);

        try {
            $response = Http::timeout($this->timeout)
                ->withBody($xml, 'application/xml')
                ->post($this->baseUrl);

            return $this->parseCreateResponse($response->body(), $response->status());
        } catch (\Exception $e) {
            Log::error('Számlázz.hu számla létrehozás hiba', ['error' => $e->getMessage()]);

            return ['success' => false, 'external_id' => null, 'invoice_number' => null, 'pdf_url' => null, 'error' => 'Kapcsolódási hiba a Számlázz.hu-hoz'];
        }
    }

    public function cancelInvoice(TabloInvoice $invoice, TabloPartner $partner): array
    {
        $apiKey = $partner->getDecryptedApiKey();
        if (! $apiKey) {
            return ['success' => false, 'external_id' => null, 'error' => 'Hiányzó API kulcs'];
        }

        $xml = $this->buildCancellationXml($invoice, $apiKey);

        try {
            $response = Http::timeout($this->timeout)
                ->withBody($xml, 'application/xml')
                ->post($this->baseUrl);

            if ($response->successful()) {
                return ['success' => true, 'external_id' => null, 'error' => null];
            }

            return ['success' => false, 'external_id' => null, 'error' => 'Sztornó hiba: '.$response->status()];
        } catch (\Exception $e) {
            Log::error('Számlázz.hu sztornó hiba', ['error' => $e->getMessage()]);

            return ['success' => false, 'external_id' => null, 'error' => 'Kapcsolódási hiba'];
        }
    }

    public function downloadPdf(TabloInvoice $invoice, TabloPartner $partner): array
    {
        $apiKey = $partner->getDecryptedApiKey();
        if (! $apiKey) {
            return ['success' => false, 'content' => null, 'error' => 'Hiányzó API kulcs'];
        }

        $xml = '<xmlszamlapdf>'.
            '<szamlaagentkulcs>'.$this->escapeXml($apiKey).'</szamlaagentkulcs>'.
            '<szamlaszam>'.$this->escapeXml($invoice->invoice_number ?? '').'</szamlaszam>'.
            '</xmlszamlapdf>';

        try {
            $response = Http::timeout($this->timeout)
                ->withBody($xml, 'application/xml')
                ->post($this->baseUrl);

            if ($response->successful()) {
                return ['success' => true, 'content' => $response->body(), 'error' => null];
            }

            return ['success' => false, 'content' => null, 'error' => 'PDF letöltés hiba'];
        } catch (\Exception $e) {
            Log::error('Számlázz.hu PDF letöltés hiba', ['error' => $e->getMessage()]);

            return ['success' => false, 'content' => null, 'error' => 'Kapcsolódási hiba'];
        }
    }

    public function validateCredentials(TabloPartner $partner): bool
    {
        $apiKey = $partner->getDecryptedApiKey();
        if (! $apiKey) {
            return false;
        }

        $xml = '<xmlszamlaxml>'.
            '<szamlaagentkulcs>'.$this->escapeXml($apiKey).'</szamlaagentkulcs>'.
            '<szamlaszam>TEST-0000</szamlaszam>'.
            '</xmlszamlaxml>';

        try {
            $response = Http::timeout($this->timeout)
                ->withBody($xml, 'application/xml')
                ->post($this->baseUrl);

            // Ha 200 jön vissza (akár hibával is), az API kulcs érvényes
            // Ha 403, az API kulcs érvénytelen
            return $response->status() !== 403;
        } catch (\Exception) {
            return false;
        }
    }

    public function getName(): string
    {
        return 'Számlázz.hu';
    }

    private function buildInvoiceXml(TabloInvoice $invoice, TabloPartner $partner, string $apiKey): string
    {
        $invoiceType = match ($invoice->type) {
            InvoiceType::PROFORMA => 'D',
            InvoiceType::DEPOSIT => 'E',
            default => '',
        };

        $items = $invoice->items->map(function ($item) {
            return '<tetel>'.
                '<megnevezes>'.$this->escapeXml($item->name).'</megnevezes>'.
                '<mennyiseg>'.$item->quantity.'</mennyiseg>'.
                '<mennyisegiEgyseg>'.$this->escapeXml($item->unit).'</mennyisegiEgyseg>'.
                '<nettoEgysegar>'.$item->unit_price.'</nettoEgysegar>'.
                '<afakulcs>'.$item->vat_percentage.'</afakulcs>'.
                '<nettoErtek>'.$item->net_amount.'</nettoErtek>'.
                '<afaErtek>'.$item->vat_amount.'</afaErtek>'.
                '<bruttoErtek>'.$item->gross_amount.'</bruttoErtek>'.
                '</tetel>';
        })->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'.
            '<xmlszamla xmlns="http://www.szamlazz.hu/xmlszamla" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'.
            '<beallitasok>'.
            '<szamlaagentkulcs>'.$this->escapeXml($apiKey).'</szamlaagentkulcs>'.
            '<eszpidf>true</eszpidf>'.
            '<szamlaLet662es>true</szamlaLet662es>'.
            '<valaszVerzio>2</valaszVerzio>'.
            '</beallitasok>'.
            '<fejlec>'.
            '<keltDatum>'.$invoice->issue_date->format('Y-m-d').'</keltDatum>'.
            '<teljesitesDatum>'.$invoice->fulfillment_date->format('Y-m-d').'</teljesitesDatum>'.
            '<fizetesiHataridoDatum>'.$invoice->due_date->format('Y-m-d').'</fizetesiHataridoDatum>'.
            '<fizmod>Átutalás</fizmod>'.
            '<ppizon>'.$invoice->currency.'</ppizon>'.
            '<szamlaNyelve>'.$partner->invoice_language.'</szamlaNyelve>'.
            '<megjegyzes>'.$this->escapeXml($invoice->comment ?? '').'</megjegyzes>'.
            ($invoiceType ? '<szamlaTipus>'.$invoiceType.'</szamlaTipus>' : '').
            '<epirefixum>'.$this->escapeXml($partner->invoice_prefix).'</epirefixum>'.
            '</fejlec>'.
            '<elado>'.
            ($partner->szamlazz_bank_name ? '<bank>'.$this->escapeXml($partner->szamlazz_bank_name).'</bank>' : '').
            ($partner->szamlazz_bank_account ? '<bankszamlaszam>'.$this->escapeXml($partner->szamlazz_bank_account).'</bankszamlaszam>' : '').
            ($partner->szamlazz_reply_email ? '<emailReplyto>'.$this->escapeXml($partner->szamlazz_reply_email).'</emailReplyto>' : '').
            '</elado>'.
            '<vevo>'.
            '<nev>'.$this->escapeXml($invoice->customer_name).'</nev>'.
            ($invoice->customer_tax_number ? '<adoszam>'.$this->escapeXml($invoice->customer_tax_number).'</adoszam>' : '').
            '<cim>'.
            '<irsz></irsz>'.
            '<telepules></telepules>'.
            '<cim>'.$this->escapeXml($invoice->customer_address ?? '').'</cim>'.
            '</cim>'.
            ($invoice->customer_email ? '<email>'.$this->escapeXml($invoice->customer_email).'</email>' : '').
            '</vevo>'.
            '<tetelek>'.$items.'</tetelek>'.
            '</xmlszamla>';
    }

    private function buildCancellationXml(TabloInvoice $invoice, string $apiKey): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'.
            '<xmlszamlast xmlns="http://www.szamlazz.hu/xmlszamlast" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'.
            '<beallitasok>'.
            '<szamlaagentkulcs>'.$this->escapeXml($apiKey).'</szamlaagentkulcs>'.
            '<eszpidf>true</eszpidf>'.
            '<valaszVerzio>2</valaszVerzio>'.
            '</beallitasok>'.
            '<fejlec>'.
            '<szamlaszam>'.$this->escapeXml($invoice->invoice_number ?? '').'</szamlaszam>'.
            '<keltDatum>'.now()->format('Y-m-d').'</keltDatum>'.
            '</fejlec>'.
            '</xmlszamlast>';
    }

    /**
     * @return array{success: bool, external_id: ?string, invoice_number: ?string, pdf_url: ?string, error: ?string}
     */
    private function parseCreateResponse(string $body, int $status): array
    {
        if ($status >= 400) {
            return ['success' => false, 'external_id' => null, 'invoice_number' => null, 'pdf_url' => null, 'error' => "HTTP hiba: {$status}"];
        }

        // Számlázz.hu a header-ben adja vissza a számla számot
        $invoiceNumber = null;
        if (preg_match('/szlahu_szamlaszam:\s*(.+)$/m', $body, $matches)) {
            $invoiceNumber = trim($matches[1]);
        }

        return [
            'success' => true,
            'external_id' => $invoiceNumber,
            'invoice_number' => $invoiceNumber,
            'pdf_url' => null,
            'error' => null,
        ];
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

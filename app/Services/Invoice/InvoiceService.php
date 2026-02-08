<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Contracts\InvoiceProviderInterface;
use App\Enums\InvoiceStatus;
use App\Enums\InvoicingProviderType;
use App\Models\TabloInvoice;
use App\Models\TabloPartner;
use App\Services\Invoice\Providers\BillingoProvider;
use App\Services\Invoice\Providers\SzamlazzProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    public function getProvider(TabloPartner $partner): InvoiceProviderInterface
    {
        return match ($partner->invoice_provider) {
            InvoicingProviderType::Billingo => new BillingoProvider(),
            default => new SzamlazzProvider(),
        };
    }

    /**
     * Számla szinkronizálása a provider-rel (draft → sent).
     */
    public function syncInvoice(TabloInvoice $invoice): TabloInvoice
    {
        $partner = $invoice->partner;
        $provider = $this->getProvider($partner);

        $invoice->loadMissing('items');

        $result = $provider->createInvoice($invoice, $partner);

        if ($result['success']) {
            $invoice->update([
                'status' => InvoiceStatus::SENT,
                'external_id' => $result['external_id'],
                'invoice_number' => $result['invoice_number'] ?? $invoice->invoice_number,
                'synced_at' => now(),
                'provider_metadata' => array_merge(
                    $invoice->provider_metadata ?? [],
                    ['last_sync' => now()->toIso8601String()]
                ),
            ]);
        } else {
            Log::warning('Számla szinkronizálás sikertelen', [
                'invoice_id' => $invoice->id,
                'error' => $result['error'],
            ]);
        }

        return $invoice->fresh();
    }

    /**
     * Számla sztornózása.
     */
    public function cancelInvoice(TabloInvoice $invoice): TabloInvoice
    {
        $partner = $invoice->partner;
        $provider = $this->getProvider($partner);

        $result = $provider->cancelInvoice($invoice, $partner);

        if ($result['success']) {
            $invoice->update([
                'status' => InvoiceStatus::CANCELLED,
                'synced_at' => now(),
            ]);
        } else {
            Log::warning('Számla sztornó sikertelen', [
                'invoice_id' => $invoice->id,
                'error' => $result['error'],
            ]);
        }

        return $invoice->fresh();
    }

    /**
     * PDF letöltése és lokális mentése.
     */
    public function downloadAndStorePdf(TabloInvoice $invoice): ?string
    {
        $partner = $invoice->partner;
        $provider = $this->getProvider($partner);

        $result = $provider->downloadPdf($invoice, $partner);

        if (! $result['success'] || ! $result['content']) {
            return null;
        }

        $disk = config('invoicing.pdf_disk', 'local');
        $basePath = config('invoicing.pdf_path', 'invoices/pdf');
        $filename = $basePath.'/'.$partner->id.'/'.$invoice->invoice_number.'.pdf';

        Storage::disk($disk)->put($filename, $result['content']);

        $invoice->update(['pdf_path' => $filename]);

        return $filename;
    }

    /**
     * API hitelesítő adatok validálása.
     */
    public function validateCredentials(TabloPartner $partner): bool
    {
        $provider = $this->getProvider($partner);

        return $provider->validateCredentials($partner);
    }

    /**
     * Számlaszám generálása: PREFIX-YYYY-NNNNN
     * Atomi: lockForUpdate + MAX-alapú sorszám.
     */
    public function generateInvoiceNumber(TabloPartner $partner): string
    {
        return DB::transaction(function () use ($partner) {
            $prefix = $partner->invoice_prefix ?? 'PS';
            $year = now()->format('Y');
            $pattern = sprintf('%s-%s-', $prefix, $year);

            $lastInvoice = TabloInvoice::where('tablo_partner_id', $partner->id)
                ->where('invoice_number', 'like', $pattern . '%')
                ->lockForUpdate()
                ->orderByDesc('invoice_number')
                ->first();

            $nextNumber = 1;
            if ($lastInvoice) {
                $lastSequence = (int) substr($lastInvoice->invoice_number, -5);
                $nextNumber = $lastSequence + 1;
            }

            return sprintf('%s-%s-%05d', $prefix, $year, $nextNumber);
        });
    }

    /**
     * Lejárt státuszú számlák frissítése.
     */
    public function updateOverdueInvoices(): int
    {
        return TabloInvoice::where('status', InvoiceStatus::SENT)
            ->where('due_date', '<', now()->startOfDay())
            ->update(['status' => InvoiceStatus::OVERDUE]);
    }
}

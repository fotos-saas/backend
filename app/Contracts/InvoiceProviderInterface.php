<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\TabloInvoice;
use App\Models\TabloPartner;

interface InvoiceProviderInterface
{
    /**
     * Számla létrehozása a provider rendszerében.
     *
     * @return array{success: bool, external_id: ?string, invoice_number: ?string, pdf_url: ?string, error: ?string}
     */
    public function createInvoice(TabloInvoice $invoice, TabloPartner $partner): array;

    /**
     * Számla sztornózása.
     *
     * @return array{success: bool, external_id: ?string, error: ?string}
     */
    public function cancelInvoice(TabloInvoice $invoice, TabloPartner $partner): array;

    /**
     * PDF letöltése bináris tartalomként.
     *
     * @return array{success: bool, content: ?string, error: ?string}
     */
    public function downloadPdf(TabloInvoice $invoice, TabloPartner $partner): array;

    /**
     * API kulcs / hitelesítő adatok validálása.
     */
    public function validateCredentials(TabloPartner $partner): bool;

    /**
     * Provider neve (megjelenítéshez).
     */
    public function getName(): string;
}

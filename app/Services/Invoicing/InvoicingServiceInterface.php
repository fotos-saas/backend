<?php

namespace App\Services\Invoicing;

use App\Models\Order;

interface InvoicingServiceInterface
{
    /**
     * Issue invoice for an order
     */
    public function issueInvoice(Order $order): array;

    /**
     * Get invoice PDF content
     */
    public function getInvoicePdf(string $invoiceId): string;

    /**
     * Cancel an issued invoice
     */
    public function cancelInvoice(string $invoiceId): bool;
}

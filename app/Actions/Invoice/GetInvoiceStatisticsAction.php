<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Enums\InvoiceStatus;
use App\Models\TabloInvoice;

class GetInvoiceStatisticsAction
{
    /**
     * Éves összesítő statisztikák.
     *
     * @return array{total_count: int, paid_count: int, pending_count: int, overdue_count: int, total_gross: float, paid_gross: float, pending_gross: float, overdue_gross: float}
     */
    public function execute(int $partnerId, ?int $year = null): array
    {
        $year = $year ?? (int) now()->format('Y');

        $invoices = TabloInvoice::where('tablo_partner_id', $partnerId)
            ->whereYear('issue_date', $year)
            ->whereNull('deleted_at')
            ->get(['status', 'gross_amount']);

        $paid = $invoices->where('status', InvoiceStatus::PAID);
        $pending = $invoices->whereIn('status', [InvoiceStatus::DRAFT, InvoiceStatus::SENT]);
        $overdue = $invoices->where('status', InvoiceStatus::OVERDUE);

        return [
            'total_count' => $invoices->count(),
            'paid_count' => $paid->count(),
            'pending_count' => $pending->count(),
            'overdue_count' => $overdue->count(),
            'total_gross' => (float) $invoices->sum('gross_amount'),
            'paid_gross' => (float) $paid->sum('gross_amount'),
            'pending_gross' => (float) $pending->sum('gross_amount'),
            'overdue_gross' => (float) $overdue->sum('gross_amount'),
        ];
    }
}

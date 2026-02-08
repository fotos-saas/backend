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

        $baseQuery = TabloInvoice::where('tablo_partner_id', $partnerId)
            ->whereYear('issue_date', $year)
            ->whereNull('deleted_at');

        $stats = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as cnt, COALESCE(SUM(gross_amount), 0) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $paidStatus = InvoiceStatus::PAID->value;
        $draftStatus = InvoiceStatus::DRAFT->value;
        $sentStatus = InvoiceStatus::SENT->value;
        $overdueStatus = InvoiceStatus::OVERDUE->value;

        $paidCount = (int) ($stats[$paidStatus]->cnt ?? 0);
        $draftCount = (int) ($stats[$draftStatus]->cnt ?? 0);
        $sentCount = (int) ($stats[$sentStatus]->cnt ?? 0);
        $overdueCount = (int) ($stats[$overdueStatus]->cnt ?? 0);

        return [
            'total_count' => $stats->sum('cnt'),
            'paid_count' => $paidCount,
            'pending_count' => $draftCount + $sentCount,
            'overdue_count' => $overdueCount,
            'total_gross' => (float) $stats->sum('total'),
            'paid_gross' => (float) ($stats[$paidStatus]->total ?? 0),
            'pending_gross' => (float) (($stats[$draftStatus]->total ?? 0) + ($stats[$sentStatus]->total ?? 0)),
            'overdue_gross' => (float) ($stats[$overdueStatus]->total ?? 0),
        ];
    }
}

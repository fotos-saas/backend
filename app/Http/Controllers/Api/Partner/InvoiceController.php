<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Actions\Invoice\CreateInvoiceAction;
use App\Actions\Invoice\GetInvoiceStatisticsAction;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Concerns\HasPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\CreateInvoiceRequest;
use App\Models\TabloInvoice;
use App\Models\TabloPartner;
use App\Services\Invoice\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    use HasPagination;
    use PartnerAuthTrait;

    public function index(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $perPage = $this->getPerPage($request);

        $query = TabloInvoice::where('tablo_partner_id', $partnerId)
            ->with(['project:id,name', 'contact:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('year')) {
            $query->whereYear('issue_date', (int) $request->input('year'));
        }

        if ($request->filled('search')) {
            $search = '%'.str_replace(['%', '_'], ['\%', '\_'], $request->input('search')).'%';
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'ILIKE', $search)
                    ->orWhere('customer_name', 'ILIKE', $search);
            });
        }

        $invoices = $query->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $invoices->items(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    public function store(CreateInvoiceRequest $request, CreateInvoiceAction $action): JsonResponse
    {
        $partner = TabloPartner::findOrFail($this->getPartnerIdOrFail());

        $result = $action->execute($partner, $request->validated());

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Számla létrehozás sikertelen',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Számla sikeresen létrehozva',
            'data' => $result['invoice'],
        ], 201);
    }

    public function show(TabloInvoice $invoice): JsonResponse
    {
        $this->authorizePartnerAccess($invoice);

        $invoice->load(['items', 'project:id,name', 'contact:id,name']);

        return response()->json(['data' => $invoice]);
    }

    public function sync(TabloInvoice $invoice, InvoiceService $invoiceService): JsonResponse
    {
        $this->authorizePartnerAccess($invoice);

        if ($invoice->status !== InvoiceStatus::DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Csak piszkozat állapotú számla szinkronizálható',
            ], 422);
        }

        $partner = TabloPartner::findOrFail($invoice->tablo_partner_id);
        if (! $partner->hasInvoicingEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'A számlázás nincs bekapcsolva vagy hiányzik az API kulcs',
            ], 422);
        }

        $invoice = $invoiceService->syncInvoice($invoice);

        return response()->json([
            'success' => $invoice->status === InvoiceStatus::SENT,
            'message' => $invoice->status === InvoiceStatus::SENT
                ? 'Számla sikeresen kiállítva'
                : 'Számla kiállítás sikertelen',
            'data' => $invoice,
        ]);
    }

    public function cancel(TabloInvoice $invoice, InvoiceService $invoiceService): JsonResponse
    {
        $this->authorizePartnerAccess($invoice);

        if ($invoice->status === InvoiceStatus::CANCELLED) {
            return response()->json([
                'success' => false,
                'message' => 'A számla már sztornózva van',
            ], 422);
        }

        $invoice = $invoiceService->cancelInvoice($invoice);

        return response()->json([
            'success' => $invoice->status === InvoiceStatus::CANCELLED,
            'message' => $invoice->status === InvoiceStatus::CANCELLED
                ? 'Számla sikeresen sztornózva'
                : 'Sztornó sikertelen',
            'data' => $invoice,
        ]);
    }

    public function downloadPdf(TabloInvoice $invoice, InvoiceService $invoiceService): Response|JsonResponse
    {
        $this->authorizePartnerAccess($invoice);

        $path = $invoiceService->downloadAndStorePdf($invoice);

        if (! $path) {
            return response()->json([
                'success' => false,
                'message' => 'PDF letöltés sikertelen',
            ], 422);
        }

        $content = \Storage::disk(config('invoicing.pdf_disk', 'local'))->get($path);

        return response($content, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$invoice->invoice_number.'.pdf"');
    }

    public function statistics(Request $request, GetInvoiceStatisticsAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $year = $request->filled('year') ? (int) $request->input('year') : null;

        return response()->json([
            'data' => $action->execute($partnerId, $year),
        ]);
    }

    private function authorizePartnerAccess(TabloInvoice $invoice): void
    {
        $partnerId = $this->getPartnerIdOrFail();

        if ($invoice->tablo_partner_id !== $partnerId) {
            abort(403, 'Nincs hozzáférésed ehhez a számlához');
        }
    }
}

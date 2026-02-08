<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Actions\Invoice\CreateInvoiceAction;
use App\Actions\Invoice\GetInvoiceStatisticsAction;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Concerns\HasPagination;
use App\Helpers\QueryHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\CreateInvoiceRequest;
use App\Models\TabloInvoice;
use App\Models\TabloPartner;
use App\Services\Invoice\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

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
            $search = QueryHelper::safeLikePattern($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'ILIKE', $search)
                    ->orWhere('customer_name', 'ILIKE', $search);
            });
        }

        $invoices = $query->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->paginatedResponse($invoices);
    }

    public function store(CreateInvoiceRequest $request, CreateInvoiceAction $action): JsonResponse
    {
        $partner = TabloPartner::findOrFail($this->getPartnerIdOrFail());

        $result = $action->execute($partner, $request->validated());

        if (! $result['success']) {
            return $this->errorResponse($result['error'] ?? 'Számla létrehozás sikertelen', 422);
        }

        return $this->createdResponse($result['invoice'], 'Számla sikeresen létrehozva');
    }

    public function show(TabloInvoice $invoice): JsonResponse
    {
        $this->authorizePartnerAccess($invoice);

        $invoice->load(['items', 'project:id,name', 'contact:id,name']);

        return $this->successResponse($invoice);
    }

    public function sync(TabloInvoice $invoice, InvoiceService $invoiceService): JsonResponse
    {
        $this->authorizePartnerAccess($invoice);

        if ($invoice->status !== InvoiceStatus::DRAFT) {
            return $this->errorResponse('Csak piszkozat állapotú számla szinkronizálható', 422);
        }

        $partner = TabloPartner::findOrFail($invoice->tablo_partner_id);
        if (! $partner->hasInvoicingEnabled()) {
            return $this->errorResponse('A számlázás nincs bekapcsolva vagy hiányzik az API kulcs', 422);
        }

        $invoice = $invoiceService->syncInvoice($invoice);

        if ($invoice->status === InvoiceStatus::SENT) {
            return $this->successResponse($invoice, 'Számla sikeresen kiállítva');
        }

        return $this->errorResponse('Számla kiállítás sikertelen', 422);
    }

    public function cancel(TabloInvoice $invoice, InvoiceService $invoiceService): JsonResponse
    {
        $this->authorizePartnerAccess($invoice);

        if ($invoice->status === InvoiceStatus::CANCELLED) {
            return $this->errorResponse('A számla már sztornózva van', 422);
        }

        $invoice = $invoiceService->cancelInvoice($invoice);

        if ($invoice->status === InvoiceStatus::CANCELLED) {
            return $this->successResponse($invoice, 'Számla sikeresen sztornózva');
        }

        return $this->errorResponse('Sztornó sikertelen', 422);
    }

    public function downloadPdf(TabloInvoice $invoice, InvoiceService $invoiceService): Response|JsonResponse
    {
        $this->authorizePartnerAccess($invoice);

        $path = $invoiceService->downloadAndStorePdf($invoice);

        if (! $path) {
            return $this->errorResponse('PDF letöltés sikertelen', 422);
        }

        $content = Storage::disk(config('invoicing.pdf_disk', 'local'))->get($path);

        $safeFilename = preg_replace('/[^A-Za-z0-9\-_]/', '_', $invoice->invoice_number);

        return response($content, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$safeFilename.'.pdf"');
    }

    public function statistics(Request $request, GetInvoiceStatisticsAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $year = $request->filled('year') ? (int) $request->input('year') : null;

        return $this->successResponse($action->execute($partnerId, $year));
    }

    private function authorizePartnerAccess(TabloInvoice $invoice): void
    {
        $partnerId = $this->getPartnerIdOrFail();

        if ($invoice->tablo_partner_id !== $partnerId) {
            abort(403, 'Nincs hozzáférésed ehhez a számlához');
        }
    }
}

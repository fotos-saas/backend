<?php

namespace App\Http\Controllers\Api\Partner;

use App\Actions\Billing\CreatePartnerChargeAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Requests\Api\Partner\StorePartnerChargeRequest;
use App\Models\GuestBillingCharge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerBillingController extends Controller
{
    use PartnerAuthTrait;

    public function index(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $query = GuestBillingCharge::query()
            ->whereHas('project', fn ($q) => $q->where('partner_id', $partnerId))
            ->with(['person:id,name', 'partnerService:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('project_id')) {
            $query->forProject((int) $request->input('project_id'));
        }

        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        }

        $charges = $query->paginate($request->input('per_page', 20));

        return $this->successResponse([
            'charges' => collect($charges->items())->map(fn ($c) => $this->formatCharge($c)),
            'pagination' => [
                'current_page' => $charges->currentPage(),
                'last_page' => $charges->lastPage(),
                'per_page' => $charges->perPage(),
                'total' => $charges->total(),
            ],
        ]);
    }

    public function store(StorePartnerChargeRequest $request, CreatePartnerChargeAction $action): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $partner = \App\Models\TabloPartner::findOrFail($partnerId);

        $charge = $action->execute($partner, $request->validated());

        return $this->successResponse([
            'charge' => $this->formatCharge($charge->load(['person:id,name', 'partnerService:id,name'])),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $charge = GuestBillingCharge::whereHas('project', fn ($q) => $q->where('partner_id', $partnerId))
            ->findOrFail($id);

        if (! $charge->isPending()) {
            return $this->errorResponse('Csak függőben lévő terhelés módosítható.', 422);
        }

        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:500'],
            'amount_huf' => ['sometimes', 'integer', 'min:1', 'max:10000000'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $charge->update($validated);

        return $this->successResponse([
            'charge' => $this->formatCharge($charge->fresh()->load(['person:id,name', 'partnerService:id,name'])),
        ]);
    }

    public function cancel(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $charge = GuestBillingCharge::whereHas('project', fn ($q) => $q->where('partner_id', $partnerId))
            ->findOrFail($id);

        if (! $charge->isPending()) {
            return $this->errorResponse('Csak függőben lévő terhelés törölhető.', 422);
        }

        $charge->update(['status' => GuestBillingCharge::STATUS_CANCELLED]);

        return $this->successResponse(['message' => 'Terhelés törölve.']);
    }

    public function summary(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $query = GuestBillingCharge::whereHas('project', fn ($q) => $q->where('partner_id', $partnerId));

        if ($request->filled('project_id')) {
            $query->forProject((int) $request->input('project_id'));
        }

        $stats = $query->selectRaw("
            COALESCE(SUM(amount_huf), 0) as total_amount,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN amount_huf ELSE 0 END), 0) as paid_amount,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN amount_huf ELSE 0 END), 0) as pending_amount,
            COUNT(*) as charges_count,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count
        ")->first();

        return $this->successResponse([
            'summary' => [
                'total_amount' => (int) $stats->total_amount,
                'paid_amount' => (int) $stats->paid_amount,
                'pending_amount' => (int) $stats->pending_amount,
                'charges_count' => (int) $stats->charges_count,
                'pending_count' => (int) $stats->pending_count,
                'paid_count' => (int) $stats->paid_count,
            ],
        ]);
    }

    private function formatCharge(GuestBillingCharge $charge): array
    {
        return [
            'id' => $charge->id,
            'charge_number' => $charge->charge_number,
            'service_type' => $charge->service_type,
            'service_label' => $charge->service_label,
            'service_name' => $charge->partnerService?->name,
            'description' => $charge->description,
            'amount_huf' => $charge->amount_huf,
            'status' => $charge->status,
            'due_date' => $charge->due_date?->toDateString(),
            'paid_at' => $charge->paid_at?->toIso8601String(),
            'person_name' => $charge->person?->name,
            'person_id' => $charge->tablo_person_id,
            'project_id' => $charge->tablo_project_id,
            'invoice_number' => $charge->invoice_number,
            'invoice_url' => $charge->invoice_url,
            'notes' => $charge->notes,
            'created_at' => $charge->created_at->toIso8601String(),
        ];
    }
}

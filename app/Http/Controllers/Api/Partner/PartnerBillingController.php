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

        return $this->successResponse([
            'summary' => [
                'total_amount' => (int) (clone $query)->sum('amount_huf'),
                'paid_amount' => (int) (clone $query)->where('status', 'paid')->sum('amount_huf'),
                'pending_amount' => (int) (clone $query)->where('status', 'pending')->sum('amount_huf'),
                'charges_count' => (clone $query)->count(),
                'pending_count' => (clone $query)->where('status', 'pending')->count(),
                'paid_count' => (clone $query)->where('status', 'paid')->count(),
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

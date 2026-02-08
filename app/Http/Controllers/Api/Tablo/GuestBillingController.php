<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Controller;
use App\Models\GuestBillingCharge;
use App\Models\TabloGuestSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestBillingController extends Controller
{
    /**
     * Vendég terheléseinek listája.
     * GET /api/tablo-frontend/billing
     */
    public function index(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;
        $personId = $this->getPersonId($request, $projectId);

        $query = GuestBillingCharge::forProject($projectId)
            ->orderByDesc('created_at');

        if ($personId) {
            $query->forPerson($personId);
        }

        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        }

        $charges = $query->get();

        return $this->success([
            'charges' => $charges->map(fn (GuestBillingCharge $c) => $this->formatCharge($c)),
        ]);
    }

    /**
     * Terhelés részletei.
     * GET /api/tablo-frontend/billing/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $charge = GuestBillingCharge::forProject($projectId)->find($id);

        if (! $charge) {
            return $this->error('Terhelés nem található.', 404);
        }

        return $this->success([
            'charge' => $this->formatCharge($charge),
        ]);
    }

    /**
     * Összesítés (total/paid/pending).
     * GET /api/tablo-frontend/billing/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;
        $personId = $this->getPersonId($request, $projectId);

        $query = GuestBillingCharge::forProject($projectId);
        if ($personId) {
            $query->forPerson($personId);
        }

        $charges = $query->get();

        $totalAmount = $charges->sum('amount_huf');
        $paidAmount = $charges->where('status', GuestBillingCharge::STATUS_PAID)->sum('amount_huf');
        $pendingAmount = $charges->where('status', GuestBillingCharge::STATUS_PENDING)->sum('amount_huf');

        return $this->success([
            'summary' => [
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'pending_amount' => $pendingAmount,
                'charges_count' => $charges->count(),
            ],
        ]);
    }

    // ============ Private ============

    private function getPersonId(Request $request, int $projectId): ?int
    {
        $guestSessionToken = $request->header('X-Guest-Session');
        if (! $guestSessionToken) {
            return null;
        }

        $session = TabloGuestSession::findByTokenAndProject($guestSessionToken, $projectId);

        return $session?->tablo_person_id;
    }

    private function formatCharge(GuestBillingCharge $charge): array
    {
        return [
            'id' => $charge->id,
            'charge_number' => $charge->charge_number,
            'service_type' => $charge->service_type,
            'service_label' => $charge->service_label,
            'description' => $charge->description,
            'amount_huf' => $charge->amount_huf,
            'status' => $charge->status,
            'due_date' => $charge->due_date?->toDateString(),
            'paid_at' => $charge->paid_at?->toIso8601String(),
            'invoice_number' => $charge->invoice_number,
            'invoice_url' => $charge->invoice_url,
            'created_at' => $charge->created_at->toIso8601String(),
        ];
    }
}

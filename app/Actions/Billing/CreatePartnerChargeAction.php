<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\GuestBillingCharge;
use App\Models\PartnerService;
use App\Models\TabloPartner;
use App\Models\TabloProject;
use Illuminate\Support\Facades\DB;

class CreatePartnerChargeAction
{
    /**
     * Partner terhelés létrehozása vendéghez.
     *
     * @param  array{
     *   tablo_project_id: int,
     *   tablo_person_id: int,
     *   partner_service_id: ?int,
     *   service_type: string,
     *   description: ?string,
     *   amount_huf: int,
     *   due_date: ?string,
     *   notes: ?string,
     * }  $data
     */
    public function execute(TabloPartner $partner, array $data): GuestBillingCharge
    {
        // Validáljuk, hogy a projekt a partnerhez tartozik
        $project = TabloProject::where('id', $data['tablo_project_id'])
            ->where('partner_id', $partner->id)
            ->firstOrFail();

        // Ha service-ből jön, az ár onnan jön (ha nem override-olták)
        if (! empty($data['partner_service_id'])) {
            $service = PartnerService::where('id', $data['partner_service_id'])
                ->where('partner_id', $partner->id)
                ->where('is_active', true)
                ->firstOrFail();
        }

        return DB::transaction(function () use ($data) {
            return GuestBillingCharge::create([
                'tablo_project_id' => $data['tablo_project_id'],
                'tablo_person_id' => $data['tablo_person_id'],
                'partner_service_id' => $data['partner_service_id'] ?? null,
                'charge_number' => GuestBillingCharge::generateChargeNumber(),
                'service_type' => $data['service_type'],
                'description' => $data['description'] ?? null,
                'amount_huf' => $data['amount_huf'],
                'status' => GuestBillingCharge::STATUS_PENDING,
                'due_date' => $data['due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
        });
    }
}

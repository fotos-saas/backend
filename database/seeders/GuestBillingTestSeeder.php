<?php

namespace Database\Seeders;

use App\Models\GuestBillingCharge;
use App\Models\TabloGuestSession;
use App\Models\TabloProject;
use Illuminate\Database\Seeder;

class GuestBillingTestSeeder extends Seeder
{
    public function run(): void
    {
        $project = TabloProject::whereHas('guestSessions')->first();

        if (! $project) {
            $this->command->warn('Nincs TabloProject GuestSession-nel. Seeder kihagyva.');
            return;
        }

        $session = TabloGuestSession::where('tablo_project_id', $project->id)->first();
        $personId = $session?->tablo_person_id;

        $charges = [
            [
                'service_type' => 'photo_change',
                'description' => 'Képcsere 2 db (egyéni portré)',
                'amount_huf' => 3500,
                'status' => 'paid',
                'paid_at' => now()->subDays(10),
                'due_date' => now()->subDays(15),
            ],
            [
                'service_type' => 'extra_retouch',
                'description' => 'Extra retusálás - bőrszín korrekció',
                'amount_huf' => 5000,
                'status' => 'paid',
                'paid_at' => now()->subDays(5),
                'due_date' => now()->subDays(10),
            ],
            [
                'service_type' => 'late_fee',
                'description' => 'Késedelmi pótdíj - határidő túllépés',
                'amount_huf' => 2000,
                'status' => 'pending',
                'due_date' => now()->addDays(7),
            ],
            [
                'service_type' => 'rush_fee',
                'description' => 'Sürgős feldolgozás - 24 órán belül',
                'amount_huf' => 8000,
                'status' => 'pending',
                'due_date' => now()->addDays(3),
            ],
            [
                'service_type' => 'additional_copy',
                'description' => 'Extra tablóminta - 1 db A3 méret',
                'amount_huf' => 4500,
                'status' => 'cancelled',
                'due_date' => now()->subDays(2),
            ],
            [
                'service_type' => 'photo_change',
                'description' => 'Csoportkép újrafotózás kérése',
                'amount_huf' => 6000,
                'status' => 'pending',
                'due_date' => now()->addDays(14),
            ],
            [
                'service_type' => 'custom',
                'description' => 'Egyedi igény: arany betűs felirat a tablóra',
                'amount_huf' => 12000,
                'status' => 'paid',
                'paid_at' => now()->subDays(1),
                'due_date' => now()->subDays(3),
            ],
        ];

        foreach ($charges as $data) {
            GuestBillingCharge::create([
                'tablo_project_id' => $project->id,
                'tablo_guest_session_id' => $session?->id,
                'tablo_person_id' => $personId,
                'charge_number' => GuestBillingCharge::generateChargeNumber(),
                'service_type' => $data['service_type'],
                'description' => $data['description'],
                'amount_huf' => $data['amount_huf'],
                'status' => $data['status'],
                'due_date' => $data['due_date'] ?? null,
                'paid_at' => $data['paid_at'] ?? null,
            ]);
        }

        $this->command->info("7 fiktív terhelés létrehozva a #{$project->id} projekthez.");
    }
}

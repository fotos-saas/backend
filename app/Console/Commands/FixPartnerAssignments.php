<?php

namespace App\Console\Commands;

use App\Models\Partner;
use App\Models\TabloPartner;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fixes partner role users who have no tablo_partner_id assigned.
 *
 * Problem: Partner role users need a tablo_partner_id to access partner endpoints,
 * but seeders and old registrations may not have set this field.
 *
 * Solution: For each partner user without tablo_partner_id:
 * - If they have a Partner record → create TabloPartner from Partner data
 * - If no Partner record → create TabloPartner from User data
 */
class FixPartnerAssignments extends Command
{
    protected $signature = 'partner:fix-assignments {--dry-run : Preview changes without applying them}';

    protected $description = 'Create TabloPartner records for partner role users without tablo_partner_id';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Find partner role users without tablo_partner_id
        $users = User::role('partner')
            ->whereNull('tablo_partner_id')
            ->with('partner')
            ->get();

        if ($users->isEmpty()) {
            $this->info('✅ Minden partner user rendelkezik tablo_partner_id-vel. Nincs teendő.');

            return self::SUCCESS;
        }

        $this->info("📋 Találtam {$users->count()} partner usert tablo_partner_id nélkül:");
        $this->newLine();

        // Show preview table
        $tableData = $users->map(fn (User $user) => [
            'ID' => $user->id,
            'Név' => $user->name,
            'Email' => $user->email,
            'Partner rekord' => $user->partner ? "Van (#{$user->partner->id})" : 'Nincs',
        ])->toArray();

        $this->table(['ID', 'Név', 'Email', 'Partner rekord'], $tableData);
        $this->newLine();

        if ($dryRun) {
            $this->info('🔍 Dry run befejeződött. Futtasd --dry-run nélkül a változtatások alkalmazásához.');

            return self::SUCCESS;
        }

        // Confirm before proceeding
        if (! $this->confirm('Folytatod a TabloPartner rekordok létrehozásával?')) {
            $this->info('Művelet megszakítva.');

            return self::SUCCESS;
        }

        // Process each user in a transaction
        $created = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($users as $user) {
                try {
                    $tabloPartner = $this->createTabloPartnerForUser($user);
                    $user->tablo_partner_id = $tabloPartner->id;
                    $user->save();

                    $this->line("  ✅ {$user->name} ({$user->email}) → TabloPartner #{$tabloPartner->id}");
                    $created++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'user' => $user,
                        'error' => $e->getMessage(),
                    ];
                    $this->error("  ❌ {$user->name} ({$user->email}): {$e->getMessage()}");
                }
            }

            if (! empty($errors)) {
                DB::rollBack();
                $this->newLine();
                $this->error('❌ Hibák történtek, a változtatások visszavonva.');

                return self::FAILURE;
            }

            DB::commit();

            $this->newLine();
            $this->info("✅ Sikeresen létrehozva {$created} TabloPartner rekord és hozzárendelve a userekhez.");

            return self::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Váratlan hiba: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Create a TabloPartner record for the given user.
     */
    private function createTabloPartnerForUser(User $user): TabloPartner
    {
        // Use Partner data if available, otherwise fall back to User data
        $partner = $user->partner;

        return TabloPartner::create([
            'name' => $partner?->company_name ?? $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? $partner?->phone,
        ]);
    }
}

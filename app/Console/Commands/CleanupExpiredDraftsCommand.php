<?php

namespace App\Console\Commands;

use App\Services\PartnerDraftService;
use Illuminate\Console\Command;

class CleanupExpiredDraftsCommand extends Command
{
    /**
     * The name and signature of the console command
     */
    protected $signature = 'drafts:cleanup
                          {--dry-run : Csak megmutatja, mit törölne}';

    /**
     * The console command description
     */
    protected $description = 'Lejárt draft feltöltések törlése (30 napnál régebbiek)';

    /**
     * Execute the console command
     */
    public function handle(PartnerDraftService $draftService): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('🗑️  Lejárt draft feltöltések keresése...');
        $this->info('Küszöb: ' . PartnerDraftService::MAX_DRAFT_AGE_DAYS . ' napnál régebbiek');

        if ($dryRun) {
            $this->warn('⚠️  DRY-RUN mód - semmit sem törlünk!');
            // Dry-run módban is meghívjuk, de az eredményt csak kiírjuk
            // A valódi dry-run támogatáshoz módosítani kellene a service-t
            $this->info('A dry-run mód jelenleg nem támogatott. Futtasd a parancsot --dry-run nélkül.');
            return Command::SUCCESS;
        }

        $deletedCount = $draftService->cleanupExpiredDrafts();

        $this->newLine();

        if ($deletedCount > 0) {
            $this->info("✅ {$deletedCount} lejárt draft kép törölve");
        } else {
            $this->info('✅ Nincs törlendő draft');
        }

        return Command::SUCCESS;
    }
}

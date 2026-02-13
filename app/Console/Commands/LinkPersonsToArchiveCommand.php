<?php

namespace App\Console\Commands;

use App\Services\ArchiveLinkingService;
use Illuminate\Console\Command;

class LinkPersonsToArchiveCommand extends Command
{
    protected $signature = 'persons:link-archive
        {--partner= : Csak egy partner ID-jához tartozó személyek}
        {--dry-run : Csak statisztika, valódi változtatás nélkül}
        {--no-auto-create : Ne hozzon létre hiányzó archive rekordokat}';

    protected $description = 'Személyek összekötése az archive rendszerrel (teacher_archive / student_archive)';

    public function handle(ArchiveLinkingService $service): int
    {
        $partnerId = $this->option('partner') ? (int) $this->option('partner') : null;
        $dryRun = (bool) $this->option('dry-run');
        $autoCreate = !$this->option('no-auto-create');

        if ($dryRun) {
            $this->info('DRY RUN mód - csak statisztika, változtatás nem történik.');
        }

        if ($partnerId) {
            $this->info("Partner #{$partnerId} személyeinek linkelése...");
            $stats = $service->linkAllForPartner($partnerId, $autoCreate, $dryRun);
        } else {
            $this->info('ÖSSZES partner személyeinek linkelése...');
            $stats = $this->linkAll($service, $autoCreate, $dryRun);
        }

        $this->newLine();
        $this->table(
            ['Statisztika', 'Darab'],
            [
                ['Már linkelt', $stats['already_linked']],
                ['Újonnan linkelt', $stats['linked']],
                ['Létrehozott archive', $stats['created']],
                ['Kihagyott (nincs match)', $stats['skipped']],
            ]
        );

        if ($dryRun) {
            $this->warn('Ez dry-run volt. Futtasd --dry-run nélkül az éles futtatáshoz.');
        } else {
            $this->info('Linkelés befejezve.');
        }

        return Command::SUCCESS;
    }

    private function linkAll(ArchiveLinkingService $service, bool $autoCreate, bool $dryRun): array
    {
        $partnerIds = \App\Models\TabloProject::distinct()->pluck('partner_id')->filter();

        $totals = ['linked' => 0, 'created' => 0, 'skipped' => 0, 'already_linked' => 0];

        $bar = $this->output->createProgressBar($partnerIds->count());
        $bar->start();

        foreach ($partnerIds as $id) {
            $stats = $service->linkAllForPartner($id, $autoCreate, $dryRun);
            foreach ($totals as $key => &$val) {
                $val += $stats[$key];
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $totals;
    }
}

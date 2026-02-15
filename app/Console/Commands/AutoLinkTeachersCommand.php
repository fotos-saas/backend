<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Teacher\TeacherAutoLinkService;
use Illuminate\Console\Command;

class AutoLinkTeachersCommand extends Command
{
    protected $signature = 'teachers:auto-link
        {--partner= : Partner ID (kötelező)}
        {--dry-run : Csak előnézet, nem módosít semmit}
        {--only-new : Csak linked_group IS NULL rekordokra fut}
        {--skip-ai : Csak determinisztikus matching, AI nélkül}';

    protected $description = 'Tanárok automatikus összekapcsolása (determinisztikus + AI)';

    public function handle(TeacherAutoLinkService $service): int
    {
        $partnerId = (int) $this->option('partner');
        if ($partnerId <= 0) {
            $this->error('A --partner opció kötelező és pozitív egésznek kell lennie.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $onlyNew = (bool) $this->option('only-new');
        $skipAi = (bool) $this->option('skip-ai');

        if ($dryRun) {
            $this->warn('*** DRY-RUN mód — nem módosít semmit ***');
        }

        $this->info("Partner: #{$partnerId}, only-new: ".($onlyNew ? 'igen' : 'nem').", skip-ai: ".($skipAi ? 'igen' : 'nem'));
        $this->newLine();

        // Analízis futtatása
        $result = $service->analyze($partnerId, $onlyNew, function (string $phase, string $message) {
            $this->line("  [{$phase}] {$message}");
        });

        $this->displayResults($result, $skipAi);

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry-run kész. A --dry-run flag eltávolításával végrehajtódik a linking.');

            return self::SUCCESS;
        }

        // Végrehajtás
        $groupsToExecute = $result['deterministic'];

        if (! $skipAi) {
            $groupsToExecute = array_merge($groupsToExecute, $result['ai']);
        }

        if (empty($groupsToExecute)) {
            $this->info('Nincs végrehajtandó linking.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Végrehajtás? (".count($groupsToExecute).' csoport)')) {
            $this->info('Megszakítva.');

            return self::SUCCESS;
        }

        $execResult = $service->execute($partnerId, $groupsToExecute);

        $this->newLine();
        $this->info('=== Végrehajtás eredménye ===');
        $this->info("Csoportok létrehozva: {$execResult['groups_created']}");
        $this->info("Tanárok összekapcsolva: {$execResult['teachers_linked']}");
        $this->info("Javaslatok mentve (manuális review): {$execResult['suggestions_saved']}");

        return self::SUCCESS;
    }

    private function displayResults(array $result, bool $skipAi): void
    {
        $stats = $result['stats'];

        $this->newLine();
        $this->info('=== Analízis eredménye ===');
        $this->table(
            ['Metrika', 'Érték'],
            [
                ['Összes tanár (scope)', $stats['total_teachers']],
                ['Determinisztikus csoportok', $stats['deterministic_groups']],
                ['Determinisztikus tanárok', $stats['deterministic_teachers']],
                ['AI csoportok (high)', $skipAi ? 'kihagyva' : $stats['ai_groups']],
                ['AI javaslatok (medium)', $skipAi ? 'kihagyva' : $stats['ai_suggested']],
                ['Nem párosított', $stats['unmatched']],
            ]
        );

        // Top 10 determinisztikus csoport kiírása
        if (! empty($result['deterministic'])) {
            $this->newLine();
            $this->info('--- Determinisztikus csoportok (első 15) ---');
            foreach (array_slice($result['deterministic'], 0, 15) as $i => $group) {
                $ids = implode(', ', $group['teacher_ids']);
                $this->line("  ".($i + 1).". [{$ids}] — {$group['reason']}");
            }
            if (count($result['deterministic']) > 15) {
                $this->line('  ... és még '.(count($result['deterministic']) - 15).' csoport');
            }
        }

        // AI csoportok
        if (! $skipAi && ! empty($result['ai'])) {
            $this->newLine();
            $this->info('--- AI csoportok (első 15) ---');
            $highGroups = array_filter($result['ai'], fn ($g) => $g['confidence'] === 'high');
            $mediumGroups = array_filter($result['ai'], fn ($g) => $g['confidence'] === 'medium');

            foreach (array_slice($highGroups, 0, 10) as $i => $group) {
                $ids = implode(', ', $group['teacher_ids']);
                $this->line("  <info>[HIGH]</info> [{$ids}] — {$group['reason']}");
            }

            foreach (array_slice($mediumGroups, 0, 5) as $i => $group) {
                $ids = implode(', ', $group['teacher_ids']);
                $this->line("  <comment>[MEDIUM]</comment> [{$ids}] — {$group['reason']}");
            }
        }
    }
}

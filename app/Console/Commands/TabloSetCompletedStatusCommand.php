<?php

namespace App\Console\Commands;

use App\Models\TabloOrderAnalysis;
use App\Models\TabloProject;
use App\Models\TabloStatus;
use Illuminate\Console\Command;

/**
 * Beállítja a "Kész" státuszt azon projektekhez, amelyeknek van lezárt megrendelés-elemzése.
 *
 * Ha van TabloOrderAnalysis rekord "completed" státusszal,
 * akkor a projekt státusza "Kész" (completed) lesz.
 */
class TabloSetCompletedStatusCommand extends Command
{
    protected $signature = 'tablo:set-completed-status
                            {--dry-run : Csak listázza a projekteket, nem módosít}';

    protected $description = 'Beállítja a Kész státuszt azon projektekhez, amelyeknek van lezárt megrendelés-elemzése (TabloOrderAnalysis)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // "Kész" státusz ID lekérése
        $completedStatus = TabloStatus::where('slug', 'completed')->first();

        if (! $completedStatus) {
            $this->error('Nem található "completed" státusz!');

            return self::FAILURE;
        }

        $this->info("🔍 Projektek keresése lezárt megrendelés-elemzéssel (TabloOrderAnalysis.status = completed)...\n");

        // Projektek lekérése ahol van completed TabloOrderAnalysis és nincs még "Kész" státusz
        $projectIdsWithCompletedAnalysis = TabloOrderAnalysis::where('status', 'completed')
            ->pluck('tablo_project_id');

        $projects = TabloProject::whereIn('id', $projectIdsWithCompletedAnalysis)
            ->where(function ($query) use ($completedStatus) {
                $query->whereNull('tablo_status_id')
                    ->orWhere('tablo_status_id', '!=', $completedStatus->id);
            })
            ->get();

        $updated = 0;

        foreach ($projects as $project) {
            $currentStatus = $project->tabloStatus?->name ?? 'NULL';

            if ($dryRun) {
                $this->line("  📋 {$project->id} - {$project->name}");
                $this->line("     Jelenlegi státusz: {$currentStatus}");
                $this->line("     → Új státusz: Kész");
                $this->newLine();
            } else {
                $project->tablo_status_id = $completedStatus->id;
                $project->save();

                $this->line("  ✅ {$project->id} - {$project->name} → Kész");
            }

            $updated++;
        }

        $this->newLine();

        // Statisztika: hány projekt van completed OrderAnalysis-sal összesen
        $totalWithCompletedAnalysis = $projectIdsWithCompletedAnalysis->count();
        $alreadyCompleted = $totalWithCompletedAnalysis - $updated;

        if ($dryRun) {
            $this->warn("🔸 DRY-RUN mód - nem történt módosítás");
            $this->info("   Frissítendő projektek: {$updated}");
            $this->info("   Már Kész státuszú: {$alreadyCompleted}");
            $this->info("   Összes completed OrderAnalysis: {$totalWithCompletedAnalysis}");
            $this->newLine();
            $this->line("Futtatás élesben: php artisan tablo:set-completed-status");
        } else {
            $this->info("✅ Összesen frissítve: {$updated} projekt");
            $this->info("   Már Kész státuszú volt: {$alreadyCompleted}");
        }

        return self::SUCCESS;
    }
}

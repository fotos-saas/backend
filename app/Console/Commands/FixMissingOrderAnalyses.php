<?php

namespace App\Console\Commands;

use App\Models\TabloOrderAnalysis;
use App\Models\TabloProject;
use Illuminate\Console\Command;

class FixMissingOrderAnalyses extends Command
{
    protected $signature = 'tablo:fix-missing-order-analyses {--dry-run : Show what would be done without making changes}';

    protected $description = 'Create missing TabloOrderAnalysis entries for projects with order_form data';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $projects = TabloProject::with('school')
            ->whereNotNull('data')
            ->whereRaw("data::text != '{}' AND data::text != 'null'")
            ->whereDoesntHave('orderAnalyses', fn ($q) => $q->where('status', 'completed'))
            ->get();

        $toFix = $projects->filter(function ($p) {
            $data = is_array($p->data) ? $p->data : json_decode($p->data, true);

            return ! empty($data['order_form']);
        });

        $this->info("Found {$toFix->count()} projects with order_form but no completed orderAnalysis");

        if ($toFix->isEmpty()) {
            $this->info('Nothing to fix!');

            return Command::SUCCESS;
        }

        foreach ($toFix as $project) {
            $schoolName = $project->school?->name ?? 'N/A';
            $this->line("  - ID {$project->id}: {$schoolName} - {$project->class_name}");

            if (! $dryRun) {
                TabloOrderAnalysis::create([
                    'tablo_project_id' => $project->id,
                    'status' => 'completed',
                    'analysis_data' => $project->data,
                ]);
            }
        }

        if ($dryRun) {
            $this->warn('DRY RUN - No changes made. Run without --dry-run to apply.');
        } else {
            $this->info("Created {$toFix->count()} TabloOrderAnalysis entries.");
        }

        return Command::SUCCESS;
    }
}

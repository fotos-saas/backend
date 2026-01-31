<?php

namespace App\Console\Commands;

use App\Models\TabloProject;
use Illuminate\Console\Command;

/**
 * Generate missing share tokens for existing TabloProjects.
 *
 * Finds all projects without a share_token and generates one,
 * enabling share_token_enabled by default.
 */
class GenerateMissingShareTokens extends Command
{
    protected $signature = 'tablo:generate-share-tokens
                            {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Generate share tokens for TabloProjects that don\'t have one';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $projects = TabloProject::whereNull('share_token')
            ->orWhere('share_token', '')
            ->get();

        if ($projects->isEmpty()) {
            $this->info('✅ Minden projektnek van már share_token-je.');

            return self::SUCCESS;
        }

        $this->info(sprintf('🔍 %d projekt share_token nélkül', $projects->count()));

        if ($dryRun) {
            $this->warn('🔸 Dry-run mód - nem történik változtatás');
            $this->newLine();

            foreach ($projects as $project) {
                $this->line(sprintf(
                    '  - #%d: %s (%s)',
                    $project->id,
                    $project->name,
                    $project->school?->name ?? 'N/A'
                ));
            }

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($projects->count());
        $bar->start();

        $updated = 0;
        foreach ($projects as $project) {
            $project->share_token = $project->generateShareToken();
            $project->share_token_enabled = true;
            $project->saveQuietly(); // Ne triggereljük az observer-eket
            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf('✅ %d projekt frissítve share_token-nel.', $updated));

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\TabloProject;
use Illuminate\Console\Command;

class GenerateProjectCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'projects:generate-codes
                            {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate access codes for all TabloProjects that do not have one';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $projects = TabloProject::whereNull('access_code')
            ->orWhere('access_code', '')
            ->get();

        if ($projects->isEmpty()) {
            $this->info('Minden projektnek van már kódja.');

            return Command::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Projektek kód nélkül: {$projects->count()}");

        $table = [];

        foreach ($projects as $project) {
            $code = $project->generateAccessCode();

            if (! $dryRun) {
                $project->update([
                    'access_code' => $code,
                    'access_code_enabled' => true,
                ]);
            }

            $table[] = [
                $project->id,
                $project->display_name,
                $code,
            ];
        }

        $this->table(['ID', 'Projekt', 'Generált kód'], $table);

        if ($dryRun) {
            $this->warn('DRY-RUN mód: változtatások nem lettek elmentve.');
        } else {
            $this->info("{$projects->count()} projekt kapott új kódot.");
        }

        return Command::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\PackagePointService;
use Illuminate\Console\Command;

class SyncPackagePoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'package-points:sync
                            {--provider= : Sync only specific provider (foxpost or packeta)}
                            {--all : Sync all providers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync package points from external APIs (Foxpost, Packeta)';

    /**
     * Execute the console command.
     */
    public function handle(PackagePointService $service): int
    {
        $provider = $this->option('provider');
        $all = $this->option('all');

        if (! $provider && ! $all) {
            $this->error('Please specify --provider=foxpost|packeta or --all');

            return self::FAILURE;
        }

        $providers = $all ? ['foxpost', 'packeta'] : [$provider];

        foreach ($providers as $providerName) {
            $this->info("Syncing {$providerName} package points...");

            if ($providerName === 'packeta') {
                // Packeta has two endpoints: branch (stores) and box (Z-BOX lockers)
                $this->line('  → Syncing Packeta branch points (stores)...');
                $branchResult = $service->syncPacketaPoints();

                $this->line('  → Syncing Packeta Z-BOX points (lockers)...');
                $boxResult = $service->syncPacketaBoxPoints();

                // Combine results
                if ($branchResult['success'] && $boxResult['success']) {
                    $totalCreated = $branchResult['created'] + $boxResult['created'];
                    $totalUpdated = $branchResult['updated'] + $boxResult['updated'];
                    $totalAll = $branchResult['total'] + $boxResult['total'];

                    $this->info("✓ {$providerName} sync completed:");
                    $this->line("  - Branch points: {$branchResult['total']} (created: {$branchResult['created']}, updated: {$branchResult['updated']})");
                    $this->line("  - Z-BOX points: {$boxResult['total']} (created: {$boxResult['created']}, updated: {$boxResult['updated']})");
                    $this->line("  - Total Created: {$totalCreated}");
                    $this->line("  - Total Updated: {$totalUpdated}");
                    $this->line("  - Grand Total: {$totalAll}");
                } else {
                    if (! $branchResult['success']) {
                        $this->error("✗ {$providerName} branch sync failed: {$branchResult['error']}");
                    }
                    if (! $boxResult['success']) {
                        $this->error("✗ {$providerName} Z-BOX sync failed: {$boxResult['error']}");
                    }
                }
            } else {
                $result = match ($providerName) {
                    'foxpost' => $service->syncFoxpostPoints(),
                    default => ['success' => false, 'error' => 'Unknown provider'],
                };

                if ($result['success']) {
                    $this->info("✓ {$providerName} sync completed:");
                    $this->line("  - Created: {$result['created']}");
                    $this->line("  - Updated: {$result['updated']}");
                    $this->line("  - Total: {$result['total']}");
                } else {
                    $this->error("✗ {$providerName} sync failed: {$result['error']}");
                }
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}

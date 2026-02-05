<?php

namespace App\Console\Commands;

use App\Models\Partner;
use App\Services\Storage\StorageUsageService;
use Illuminate\Console\Command;

class RecalculateStorageUsage extends Command
{
    protected $signature = 'storage:recalculate {--partner-id= : Egy adott partner ID}';

    protected $description = 'Partner tárhely használat újraszámolása és cache-elése';

    public function handle(StorageUsageService $storageService): int
    {
        if ($partnerId = $this->option('partner-id')) {
            return $this->recalculateForPartner($storageService, (int) $partnerId);
        }

        return $this->recalculateAll($storageService);
    }

    private function recalculateForPartner(StorageUsageService $storageService, int $partnerId): int
    {
        $partner = Partner::find($partnerId);

        if (! $partner) {
            $this->error("Partner #{$partnerId} nem található.");

            return Command::FAILURE;
        }

        $bytes = $storageService->recalculateAndCache($partner);
        $gb = round($bytes / (1024 * 1024 * 1024), 2);

        $this->info("Partner #{$partnerId} ({$partner->company_name}): {$gb} GB ({$bytes} bytes)");

        return Command::SUCCESS;
    }

    private function recalculateAll(StorageUsageService $storageService): int
    {
        $count = Partner::whereNull('deleted_at')->count();
        $this->info("Tárhely újraszámolás {$count} partnerhez...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $totalBytes = 0;
        $processed = 0;

        Partner::whereNull('deleted_at')->cursor()->each(function (Partner $partner) use ($storageService, &$totalBytes, &$processed, $bar) {
            $bytes = $storageService->recalculateAndCache($partner);
            $totalBytes += $bytes;
            $processed++;
            $bar->advance();
        });

        $bar->finish();
        $this->newLine(2);

        $totalGb = round($totalBytes / (1024 * 1024 * 1024), 2);
        $this->info("Kész! {$processed} partner feldolgozva. Összesen: {$totalGb} GB");

        return Command::SUCCESS;
    }
}

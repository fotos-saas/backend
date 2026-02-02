<?php

namespace App\Console\Commands;

use App\Models\Partner;
use App\Models\SubscriptionDiscount;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupDeletedAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounts:cleanup-deleted {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently delete accounts that have been scheduled for deletion for 30+ days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('Running in dry-run mode - no data will be deleted');
        }

        // Find partners scheduled for deletion that have passed their deletion date
        $partnersToDelete = Partner::onlyTrashed()
            ->whereNotNull('deletion_scheduled_at')
            ->where('deletion_scheduled_at', '<', now())
            ->get();

        if ($partnersToDelete->isEmpty()) {
            $this->info('No accounts to clean up.');
            return Command::SUCCESS;
        }

        $this->info("Found {$partnersToDelete->count()} account(s) to permanently delete.");

        foreach ($partnersToDelete as $partner) {
            $this->line("Processing partner ID: {$partner->id} (User: {$partner->user_id})");

            if ($isDryRun) {
                $this->warn("  [DRY RUN] Would delete partner {$partner->id} and user {$partner->user_id}");
                continue;
            }

            try {
                DB::transaction(function () use ($partner) {
                    // Delete subscription discounts
                    SubscriptionDiscount::where('partner_id', $partner->id)->forceDelete();

                    // Get user
                    $user = User::withTrashed()->find($partner->user_id);

                    // Delete any tokens
                    if ($user) {
                        $user->tokens()->delete();
                    }

                    // Force delete partner
                    $partner->forceDelete();

                    // Force delete user
                    if ($user) {
                        $user->forceDelete();
                    }

                    Log::info('Account permanently deleted', [
                        'partner_id' => $partner->id,
                        'user_id' => $partner->user_id,
                    ]);
                });

                $this->info("  Successfully deleted partner {$partner->id}");

            } catch (\Exception $e) {
                $this->error("  Failed to delete partner {$partner->id}: {$e->getMessage()}");

                Log::error('Failed to permanently delete account', [
                    'partner_id' => $partner->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info('Cleanup completed.');

        return Command::SUCCESS;
    }
}

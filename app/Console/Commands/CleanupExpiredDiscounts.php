<?php

namespace App\Console\Commands;

use App\Models\SubscriptionDiscount;
use Illuminate\Console\Command;

class CleanupExpiredDiscounts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'discounts:cleanup-expired';

    /**
     * The console command description.
     */
    protected $description = 'Soft delete expired subscription discounts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expired = SubscriptionDiscount::query()
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', now())
            ->whereNull('deleted_at')
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired discounts found.');

            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($expired as $discount) {
            $discount->delete();
            $this->info("Expired discount #{$discount->id} for partner #{$discount->partner_id} cleaned up.");
            $count++;
        }

        $this->info("Total: {$count} expired discount(s) cleaned up.");

        return Command::SUCCESS;
    }
}

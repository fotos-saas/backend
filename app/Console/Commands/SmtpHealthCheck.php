<?php

namespace App\Console\Commands;

use App\Mail\TemplateMail;
use App\Models\SmtpAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SmtpHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smtp:health-check {--smtp-account-id= : Check specific SMTP account only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check health status of SMTP accounts by sending test emails';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $accountId = $this->option('smtp-account-id');

        if ($accountId) {
            $accounts = SmtpAccount::where('id', $accountId)->get();

            if ($accounts->isEmpty()) {
                $this->error("SMTP account with ID {$accountId} not found.");

                return self::FAILURE;
            }
        } else {
            $accounts = SmtpAccount::where('is_active', true)->get();
        }

        if ($accounts->isEmpty()) {
            $this->warn('No SMTP accounts to check.');

            return self::SUCCESS;
        }

        $this->info('Checking health of '.$accounts->count().' SMTP account(s)...');
        $this->newLine();

        foreach ($accounts as $account) {
            $this->checkAccount($account);
        }

        $this->newLine();
        $this->info('Health check completed!');

        return self::SUCCESS;
    }

    /**
     * Check health of a single SMTP account
     */
    protected function checkAccount(SmtpAccount $account): void
    {
        $this->line("Checking: <fg=cyan>{$account->name}</> [{$account->mailer_type}]");

        try {
            $testRecipient = config('mail.test_recipient') ?? config('mail.from.address');

            if (! $testRecipient) {
                throw new \Exception('No test recipient configured. Set MAIL_TEST_RECIPIENT in .env');
            }

            // Create test email
            $subject = "SMTP Health Check - {$account->name}";
            $body = "<h3>SMTP Health Check</h3>
                     <p>This is an automated health check email.</p>
                     <p><strong>SMTP Account:</strong> {$account->name}</p>
                     <p><strong>Time:</strong> ".now()->format('Y-m-d H:i:s').'</p>';

            $mail = new TemplateMail($subject, $body, []);

            // Send test email using this SMTP account
            $mailerName = $account->getDynamicMailerName();
            Mail::mailer($mailerName)->to($testRecipient)->send($mail);

            // Update account as healthy
            $account->update([
                'health_status' => 'healthy',
                'last_health_check_at' => now(),
                'health_error_message' => null,
            ]);

            $this->line("  <fg=green>✓</> Healthy - test email sent to {$testRecipient}");

        } catch (Throwable $exception) {
            // Update account as error
            $account->update([
                'health_status' => 'error',
                'last_health_check_at' => now(),
                'health_error_message' => $exception->getMessage(),
            ]);

            $this->line('  <fg=red>✗</> Error: '.$exception->getMessage());
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendMagicLinkEmailJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public EmailTemplate $template,
        public array $variables,
        public string $eventType = 'user_magic_login'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(EmailService $emailService): void
    {
        try {
            $emailService->sendFromTemplate(
                template: $this->template,
                recipientEmail: $this->user->email,
                variables: $this->variables,
                recipientUser: $this->user,
                eventType: $this->eventType
            );

            Log::info('Magic link email sent via job', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send magic link email via job', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger job retry
        }
    }
}

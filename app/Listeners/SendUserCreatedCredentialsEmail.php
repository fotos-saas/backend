<?php

namespace App\Listeners;

use App\Events\UserCreatedWithCredentials;
use App\Models\EmailEvent;
use App\Services\EmailService;
use App\Services\EmailVariableService;

class SendUserCreatedCredentialsEmail
{
    public function __construct(
        protected EmailService $emailService,
        protected EmailVariableService $variableService,
    ) {}

    public function handle(UserCreatedWithCredentials $event): void
    {
        $emailEvents = EmailEvent::with(['emailTemplate'])
            ->where('event_type', 'user_created_credentials')
            ->where('is_active', true)
            ->get();

        if ($emailEvents->isEmpty()) {
            return;
        }

        $variables = $this->variableService->resolveVariables(
            user: $event->user,
            album: $event->album,
            authData: ['password' => $event->password],
        );

        foreach ($emailEvents as $emailEvent) {
            $recipients = $this->emailService->getEventRecipients($emailEvent, [
                'user' => $event->user,
                'album' => $event->album,
            ]);

            foreach ($recipients as $recipient) {
                $email = $recipient instanceof \App\Models\User ? $recipient->email : $recipient;
                $this->emailService->sendFromTemplate(
                    template: $emailEvent->emailTemplate,
                    recipientEmail: $email,
                    variables: $variables,
                    recipientUser: $recipient instanceof \App\Models\User ? $recipient : null,
                    eventType: 'user_created_credentials',
                    attachments: $emailEvent->attachments ?? [],
                );
            }
        }
    }
}


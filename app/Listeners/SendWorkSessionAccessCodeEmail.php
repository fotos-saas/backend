<?php

namespace App\Listeners;

use App\Events\WorkSessionCreated;
use App\Models\EmailEvent;
use App\Services\EmailService;
use App\Services\EmailVariableService;

class SendWorkSessionAccessCodeEmail
{
    public function __construct(
        protected EmailService $emailService,
        protected EmailVariableService $variableService,
    ) {}

    public function handle(WorkSessionCreated $event): void
    {
        $emailEvents = EmailEvent::with(['emailTemplate'])
            ->where('event_type', 'work_session_created')
            ->where('is_active', true)
            ->get();

        if ($emailEvents->isEmpty()) {
            return;
        }

        $variables = $this->variableService->resolveVariables(
            user: $event->user,
            workSession: $event->workSession,
        );

        foreach ($emailEvents as $emailEvent) {
            $recipients = $this->emailService->getEventRecipients($emailEvent, [
                'user' => $event->user,
            ]);

            foreach ($recipients as $recipient) {
                $email = $recipient instanceof \App\Models\User ? $recipient->email : $recipient;
                $this->emailService->sendFromTemplate(
                    template: $emailEvent->emailTemplate,
                    recipientEmail: $email,
                    variables: $variables,
                    recipientUser: $recipient instanceof \App\Models\User ? $recipient : null,
                    eventType: 'work_session_created',
                    attachments: $emailEvent->attachments ?? [],
                );
            }
        }
    }
}


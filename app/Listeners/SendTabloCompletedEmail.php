<?php

namespace App\Listeners;

use App\Events\TabloWorkflowCompleted;
use App\Models\EmailEvent;
use App\Services\EmailService;
use App\Services\EmailVariableService;
use App\Services\MagicLinkService;

class SendTabloCompletedEmail
{
    public function __construct(
        protected EmailService $emailService,
        protected EmailVariableService $variableService,
        protected MagicLinkService $magicLinkService,
    ) {}

    public function handle(TabloWorkflowCompleted $event): void
    {
        $emailEvents = EmailEvent::with(['emailTemplate'])
            ->where('event_type', 'tablo_completed')
            ->where('is_active', true)
            ->get();

        if ($emailEvents->isEmpty()) {
            return;
        }

        // Generate 6-month magic link for work session access
        $magicLinkData = $this->magicLinkService->generateForWorkSession(
            user: $event->user,
            workSession: $event->workSession,
            expirationDays: 180 // 6 months
        );

        $variables = $this->variableService->resolveVariables(
            user: $event->user,
            workSession: $event->workSession,
        );

        // Add tablo completion specific variables
        $variables['tablo_photo_url'] = $event->selectedPhoto->getFirstMediaUrl('photo');
        $variables['tablo_photo_thumb_url'] = $event->selectedPhoto->getThumbUrl() ?? $event->selectedPhoto->getFirstMediaUrl('photo');
        $variables['tablo_photo_id'] = $event->selectedPhoto->id;
        $variables['magic_link_worksession'] = $magicLinkData['url'];
        $variables['work_session_name'] = $event->workSession->name;
        $variables['completion_date'] = now()->format('Y-m-d H:i');

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
                    eventType: 'tablo_completed',
                    attachments: $emailEvent->attachments ?? [],
                );
            }
        }
    }
}

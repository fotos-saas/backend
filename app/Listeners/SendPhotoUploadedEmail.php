<?php

namespace App\Listeners;

use App\Events\PhotoUploaded;
use App\Models\EmailEvent;
use App\Services\EmailService;
use App\Services\EmailVariableService;

class SendPhotoUploadedEmail
{
    public function __construct(
        protected EmailService $emailService,
        protected EmailVariableService $variableService,
    ) {}

    public function handle(PhotoUploaded $event): void
    {
        $emailEvents = EmailEvent::with(['emailTemplate'])
            ->where('event_type', 'photo_uploaded')
            ->where('is_active', true)
            ->get();

        if ($emailEvents->isEmpty()) {
            return;
        }

        $album = $event->photo->album;
        $user = $event->photo->assignedUser;

        $variables = $this->variableService->resolveVariables(
            user: $user,
            album: $album,
        );

        $variables = array_merge($variables, [
            'photo_id' => $event->photo->id,
            'photo_uploaded_at' => $event->photo->created_at?->format('Y-m-d H:i'),
        ]);

        foreach ($emailEvents as $emailEvent) {
            $recipients = $this->emailService->getEventRecipients($emailEvent, [
                'album' => $album,
                'user' => $user,
            ]);

            foreach ($recipients as $recipient) {
                $email = $recipient instanceof \App\Models\User ? $recipient->email : $recipient;

                $this->emailService->sendFromTemplate(
                    template: $emailEvent->emailTemplate,
                    recipientEmail: $email,
                    variables: $variables,
                    recipientUser: $recipient instanceof \App\Models\User ? $recipient : null,
                    eventType: 'photo_uploaded',
                    attachments: $emailEvent->attachments ?? [],
                );
            }
        }
    }
}

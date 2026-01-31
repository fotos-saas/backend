<?php

namespace App\Listeners;

use App\Events\OrderShipped;
use App\Models\EmailEvent;
use App\Services\EmailService;
use App\Services\EmailVariableService;

class SendOrderShippedEmail
{
    public function __construct(
        protected EmailService $emailService,
        protected EmailVariableService $variableService,
    ) {}

    public function handle(OrderShipped $event): void
    {
        $emailEvents = EmailEvent::with(['emailTemplate'])
            ->where('event_type', 'order_shipped')
            ->where('is_active', true)
            ->get();

        if ($emailEvents->isEmpty()) {
            return;
        }

        $variables = $this->variableService->resolveVariables(
            order: $event->order,
            user: $event->order->user,
        );

        // Override tracking_number if provided in event
        if ($event->trackingNumber) {
            $variables['tracking_number'] = $event->trackingNumber;
        }

        foreach ($emailEvents as $emailEvent) {
            $recipients = $this->emailService->getEventRecipients($emailEvent, [
                'order' => $event->order,
                'user' => $event->order->user,
            ]);

            foreach ($recipients as $recipient) {
                $email = $recipient instanceof \App\Models\User ? $recipient->email : $recipient;
                $this->emailService->sendFromTemplate(
                    template: $emailEvent->emailTemplate,
                    recipientEmail: $email,
                    variables: $variables,
                    recipientUser: $recipient instanceof \App\Models\User ? $recipient : null,
                    eventType: 'order_shipped',
                    attachments: $emailEvent->attachments ?? [],
                );
            }
        }
    }
}


<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Models\EmailEvent;
use App\Services\EmailService;
use App\Services\EmailVariableService;

class SendOrderStatusChangedEmail
{
    public function __construct(
        protected EmailService $emailService,
        protected EmailVariableService $variableService,
    ) {}

    public function handle(OrderStatusChanged $event): void
    {
        $emailEvents = EmailEvent::with(['emailTemplate'])
            ->where('event_type', 'order_status_changed')
            ->where('is_active', true)
            ->get();

        if ($emailEvents->isEmpty()) {
            return;
        }

        $variables = $this->variableService->resolveVariables(
            user: $event->order->user,
            order: $event->order,
        );

        $variables = array_merge($variables, [
            'order_previous_status' => $event->previousStatus,
            'order_new_status' => $event->newStatus,
        ]);

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
                    eventType: 'order_status_changed',
                    attachments: $emailEvent->attachments ?? [],
                );
            }
        }
    }
}

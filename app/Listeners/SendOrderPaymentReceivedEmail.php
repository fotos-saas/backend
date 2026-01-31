<?php

namespace App\Listeners;

use App\Events\OrderPaymentReceived;
use App\Models\EmailEvent;
use App\Services\EmailService;
use App\Services\EmailVariableService;

class SendOrderPaymentReceivedEmail
{
    public function __construct(
        protected EmailService $emailService,
        protected EmailVariableService $variableService,
    ) {}

    public function handle(OrderPaymentReceived $event): void
    {
        $emailEvents = EmailEvent::with(['emailTemplate'])
            ->where('event_type', 'order_payment_received')
            ->where('is_active', true)
            ->get();

        if ($emailEvents->isEmpty()) {
            return;
        }

        $variables = $this->variableService->resolveVariables(
            order: $event->order,
            user: $event->order->user,
        );

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
                    eventType: 'order_payment_received',
                    attachments: $emailEvent->attachments ?? [],
                );
            }
        }
    }
}


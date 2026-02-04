<?php

namespace App\Mail;

use App\Models\PartnerInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Csapattag meghívó email
 */
class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly PartnerInvitation $invitation
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Meghívó: ' . $this->invitation->partner->name . ' csapatába',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.team-invitation',
            with: [
                'partnerName' => $this->invitation->partner->name,
                'roleName' => $this->invitation->role_name,
                'code' => $this->invitation->code,
                'registerUrl' => $this->invitation->getRegistrationUrl(),
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}

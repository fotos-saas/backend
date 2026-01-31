<?php

namespace App\Mail;

use App\Models\TabloGuestSession;
use App\Models\TabloProject;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Guest Session Restore Mail
 *
 * Magic link email küldése a guest session helyreállításához.
 */
class GuestSessionRestoreMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public TabloGuestSession $session,
        public TabloProject $project,
        public string $restoreLink
    ) {}

    public function envelope(): Envelope
    {
        $projectName = $this->project->display_name;

        return new Envelope(
            subject: "Belépés - {$projectName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.guest-session-restore',
            with: [
                'guestName' => $this->session->guest_name,
                'projectName' => $this->project->display_name,
                'schoolName' => $this->project->school?->name,
                'link' => $this->restoreLink,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

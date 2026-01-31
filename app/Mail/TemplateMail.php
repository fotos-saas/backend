<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    protected string $subjectLine;

    protected string $body;

    protected array $attachmentPaths;

    public function __construct(string $subjectLine, string $body, array $attachments = [])
    {
        $this->subjectLine = $subjectLine;
        $this->body = $body;
        $this->attachmentPaths = $attachments;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.template',
            with: [
                'body' => $this->body,
            ],
        );
    }

    public function attachments(): array
    {
        return collect($this->attachmentPaths)
            ->map(static function (string $path) {
                return \Illuminate\Mail\Mailables\Attachment::fromStorageDisk('private', $path);
            })
            ->all();
    }
}

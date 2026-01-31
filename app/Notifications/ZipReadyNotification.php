<?php

namespace App\Notifications;

use App\Models\WorkSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class ZipReadyNotification extends Notification
{
    use Queueable;

    /**
     * @param WorkSession $workSession
     * @param string $storagePath ZIP fájl storage path
     * @param string $filename ZIP fájlnév
     */
    public function __construct(
        public WorkSession $workSession,
        public string $storagePath,
        public string $filename
    ) {
        // Send immediately (not queued)
    }

    /**
     * Get the notification's delivery channels
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Generate signed URL (valid for 24 hours)
        $downloadUrl = URL::temporarySignedRoute(
            'api.work-sessions.download-ready-zip',
            now()->addHours(24),
            [
                'storagePath' => encrypt($this->storagePath),
                'filename' => $this->filename,
            ]
        );

        return (new MailMessage)
            ->subject('ZIP fájl elkészült - ' . $this->workSession->name)
            ->greeting('Üdv ' . $notifiable->name . '!')
            ->line('A kért ZIP fájl elkészült és letölthető.')
            ->line('**Munkamenet:** ' . $this->workSession->name)
            ->line('**Fájlnév:** ' . $this->filename)
            ->action('ZIP letöltése', $downloadUrl)
            ->line('A letöltési link 24 órán keresztül érvényes.')
            ->line('A ZIP fájl automatikusan törlődik 24 óra után.')
            ->salutation('Üdvözlettel, Photo Stack Csapat');
    }

    /**
     * Get the array representation of the notification (Database)
     */
    public function toArray(object $notifiable): array
    {
        // Generate signed URL for Filament notification
        $downloadUrl = URL::temporarySignedRoute(
            'api.work-sessions.download-ready-zip',
            now()->addHours(24),
            [
                'storagePath' => encrypt($this->storagePath),
                'filename' => $this->filename,
            ]
        );

        return [
            'title' => 'ZIP fájl elkészült',
            'message' => "A kért ZIP fájl ({$this->filename}) elkészült és letölthető.",
            'work_session_id' => $this->workSession->id,
            'work_session_name' => $this->workSession->name,
            'filename' => $this->filename,
            'download_url' => $downloadUrl,
            'expires_at' => now()->addHours(24)->toIso8601String(),
        ];
    }
}

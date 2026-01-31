<?php

namespace App\Jobs;

use App\Models\ProjectEmail;
use App\Models\TabloContact;
use App\Models\TabloProject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;

class SyncEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    private const CACHE_KEY_LAST_SYNC = 'email_sync:last_sync';

    private const CACHE_KEY_LOCK = 'email_sync:lock';

    public function __construct()
    {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        // Prevent concurrent runs
        if (!Cache::add(self::CACHE_KEY_LOCK, true, 300)) {
            Log::info('SyncEmailsJob: Already running, skipping...');
            return;
        }

        try {
            $this->syncEmails();
        } finally {
            Cache::forget(self::CACHE_KEY_LOCK);
        }
    }

    private function syncEmails(): void
    {
        $lastSync = Cache::get(self::CACHE_KEY_LAST_SYNC);
        $sinceDate = $lastSync
            ? \Carbon\Carbon::parse($lastSync)->subMinutes(5) // 5 perc átfedés biztonsági okokból
            : now()->subDays(7);

        Log::info("SyncEmailsJob: Starting sync since {$sinceDate->toDateTimeString()}");

        $stats = [
            'new' => 0,
            'skipped' => 0,
            'linked' => 0,
            'errors' => 0,
        ];

        try {
            $client = Client::account('default');
            $client->connect();

            // INBOX sync
            $this->syncFolder($client, 'INBOX', 'inbound', $sinceDate, $stats);

            // Sent folder sync
            $sentFolders = ['INBOX.Sent', 'Sent', 'INBOX/Sent', 'Sent Items'];
            foreach ($sentFolders as $sentFolder) {
                try {
                    $this->syncFolder($client, $sentFolder, 'outbound', $sinceDate, $stats);
                    break;
                } catch (\Exception $e) {
                    continue;
                }
            }

            // "!!!Tabló megrendelés" folder sync - megrendelőlapok
            $orderFolders = [
                'INBOX.!!!Tabló megrendelés',
                '!!!Tabló megrendelés',
                'INBOX/!!!Tabló megrendelés',
            ];
            foreach ($orderFolders as $orderFolder) {
                try {
                    $this->syncFolder($client, $orderFolder, 'inbound', $sinceDate, $stats);
                    Log::info("SyncEmailsJob: Megrendelés folder synced: {$orderFolder}");
                    break;
                } catch (\Exception $e) {
                    continue;
                }
            }

            $client->disconnect();

            // Update last sync timestamp
            Cache::put(self::CACHE_KEY_LAST_SYNC, now()->toDateTimeString(), now()->addDays(7));

            Log::info("SyncEmailsJob: Completed", $stats);

        } catch (\Exception $e) {
            Log::error("SyncEmailsJob: Error - {$e->getMessage()}");
            throw $e;
        }
    }

    private function syncFolder($client, string $folderName, string $direction, \Carbon\Carbon $since, array &$stats): void
    {
        try {
            $folder = $client->getFolder($folderName);
            if (!$folder) {
                return;
            }
        } catch (\Exception $e) {
            return;
        }

        $messages = $folder->messages()
            ->since($since->format('d-M-Y'))
            ->setFetchBody(true)
            ->setFetchFlags(true)
            ->get();

        Log::debug("SyncEmailsJob: Found {$messages->count()} messages in {$folderName}");

        foreach ($messages as $message) {
            try {
                $this->processMessage($message, $direction, $folderName, $stats);
            } catch (\Exception $e) {
                Log::warning("SyncEmailsJob: Failed to process message - {$e->getMessage()}");
                $stats['errors']++;
            }
        }
    }

    private function processMessage($message, string $direction, string $folder, array &$stats): void
    {
        $messageId = $message->getMessageId()?->toString();

        if (!$messageId) {
            $messageId = md5($message->getSubject() . $message->getDate()->toString());
        }

        // Already exists?
        if (ProjectEmail::where('message_id', $messageId)->exists()) {
            $stats['skipped']++;
            return;
        }

        $from = $message->getFrom()->first();
        $to = $message->getTo()->first();

        $attachments = $this->extractAttachments($message);

        $data = [
            'message_id' => $messageId,
            'thread_id' => $this->extractThreadId($message),
            'in_reply_to' => $message->getInReplyTo()?->toString(),
            'from_email' => $from?->mail ?? '',
            'from_name' => $this->decodeMimeHeader($from?->personal),
            'to_email' => $to?->mail ?? '',
            'to_name' => $this->decodeMimeHeader($to?->personal),
            'cc' => $this->extractCc($message),
            'subject' => $this->decodeMimeHeader($message->getSubject()?->toString()) ?? '(Nincs tárgy)',
            'body_text' => $this->getBodyText($message),
            'body_html' => $this->getBodyHtml($message),
            'direction' => $direction,
            'is_read' => $message->getFlags()->has('seen'),
            'needs_reply' => $direction === 'inbound',
            'is_replied' => $message->getFlags()->has('answered'),
            'attachments' => $attachments,
            'imap_uid' => $message->getUid(),
            'imap_folder' => $folder,
            'email_date' => $message->getDate()?->toDate(),
        ];

        // Auto-link to project
        $projectId = $this->findProjectByEmail($data['from_email'], $data['to_email'], $data['subject']);
        if ($projectId) {
            $data['tablo_project_id'] = $projectId;
            $stats['linked']++;
        }

        $email = ProjectEmail::create($data);
        $stats['new']++;

        // Ha megrendelés mappából jön és van PDF csatolmány, indítsuk el az elemzést
        if ($this->isOrderFolder($folder) && $this->hasPdfAttachment($attachments)) {
            Log::info('SyncEmailsJob: Dispatching order analysis', [
                'email_id' => $email->id,
                'folder' => $folder,
            ]);
            ProcessOrderEmailJob::dispatch($email->id);
        }
    }

    private function extractThreadId($message): ?string
    {
        $references = $message->getReferences();
        if ($references && $references->count() > 0) {
            return $references->first();
        }

        $inReplyTo = $message->getInReplyTo();
        if ($inReplyTo) {
            return $inReplyTo->toString();
        }

        return null;
    }

    private function extractCc($message): ?array
    {
        $cc = $message->getCc();
        if (!$cc || $cc->count() === 0) {
            return null;
        }

        $result = [];
        foreach ($cc as $address) {
            $result[] = [
                'email' => $address->mail,
                'name' => $this->decodeMimeHeader($address->personal),
            ];
        }

        return $result;
    }

    private function getBodyText($message): ?string
    {
        try {
            return $message->getTextBody();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getBodyHtml($message): ?string
    {
        try {
            return $message->getHTMLBody();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function extractAttachments($message): ?array
    {
        $attachments = $message->getAttachments();
        if (!$attachments || $attachments->count() === 0) {
            return null;
        }

        $result = [];
        foreach ($attachments as $attachment) {
            $result[] = [
                'name' => $attachment->getName(),
                'size' => $attachment->getSize(),
                'mime_type' => $attachment->getMimeType(),
            ];
        }

        return $result;
    }

    private function decodeMimeHeader(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (str_contains($value, '=?')) {
            $decoded = iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

            if ($decoded === false || $decoded === $value) {
                $decoded = mb_decode_mimeheader($value);
            }

            return $decoded ?: $value;
        }

        return $value;
    }

    private function findProjectByEmail(string $fromEmail, string $toEmail, string $subject): ?int
    {
        // Search by contact email
        $contact = TabloContact::where('email', $fromEmail)
            ->orWhere('email', $toEmail)
            ->first();

        if ($contact && $contact->tablo_project_id) {
            return $contact->tablo_project_id;
        }

        // Search by project ID in subject
        if (preg_match('/\[?PRJ[#-]?(\d+)\]?/i', $subject, $matches)) {
            $project = TabloProject::find($matches[1]);
            if ($project) {
                return $project->id;
            }
        }

        // Search by external_id in subject
        if (preg_match('/\[?EXT[#-]?(\d+)\]?/i', $subject, $matches)) {
            $project = TabloProject::where('external_id', $matches[1])->first();
            if ($project) {
                return $project->id;
            }
        }

        return null;
    }

    /**
     * Ellenőrzi, hogy a mappa megrendelés mappa-e.
     */
    private function isOrderFolder(string $folder): bool
    {
        $orderFolders = [
            'INBOX.!!!Tabló megrendelés',
            '!!!Tabló megrendelés',
            'INBOX/!!!Tabló megrendelés',
        ];

        return in_array($folder, $orderFolders, true);
    }

    /**
     * Ellenőrzi, hogy van-e PDF csatolmány.
     */
    private function hasPdfAttachment(?array $attachments): bool
    {
        if (empty($attachments)) {
            return false;
        }

        foreach ($attachments as $attachment) {
            $name = strtolower($attachment['name'] ?? '');
            $mimeType = strtolower($attachment['mime_type'] ?? '');

            if (str_ends_with($name, '.pdf') || str_contains($mimeType, 'pdf')) {
                return true;
            }
        }

        return false;
    }
}

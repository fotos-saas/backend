<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOrderEmailJob;
use App\Jobs\SyncEmailsJob;
use App\Models\ProjectEmail;
use App\Models\TabloContact;
use App\Models\TabloProject;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Webklex\IMAP\Facades\Client;

class SyncEmails extends Command
{
    protected $signature = 'emails:sync
                            {--days=7 : Hány napra visszamenőleg szinkronizáljon}
                            {--folder=INBOX : Melyik mappát szinkronizálja}
                            {--sent : Elküldött emailek szinkronizálása is}
                            {--dry-run : Csak kiírja, mit csinálna}
                            {--dispatch : Queue job-ként indítja (háttérben)}
                            {--status : Utolsó szinkronizálás státusza}';

    protected $description = 'Email fiók szinkronizálása IMAP-on keresztül';

    private int $newCount = 0;

    private int $skippedCount = 0;

    private int $linkedCount = 0;

    public function handle(): int
    {
        // Status option - show last sync info
        if ($this->option('status')) {
            return $this->showStatus();
        }

        // Dispatch option - run as queue job
        if ($this->option('dispatch')) {
            SyncEmailsJob::dispatch();
            $this->info('Email szinkronizálás job elindítva a queue-ban.');
            $this->info('Használd a `php artisan queue:work --queue=emails` parancsot a feldolgozáshoz.');

            return Command::SUCCESS;
        }

        $days = (int) $this->option('days');
        $folder = $this->option('folder');
        $includeSent = $this->option('sent');
        $dryRun = $this->option('dry-run');

        $this->info('Email szinkronizálás indítása...');
        $this->info("Beállítások: {$days} nap, mappa: {$folder}, sent: ".($includeSent ? 'igen' : 'nem'));

        if ($dryRun) {
            $this->warn('DRY-RUN mód - nem történik tényleges mentés!');
        }

        try {
            $client = Client::account('default');
            $client->connect();

            $this->info('Sikeresen csatlakozva az IMAP szerverhez.');

            // INBOX szinkronizálás
            $this->syncFolder($client, $folder, 'inbound', $days, $dryRun);

            // "!!!Tabló megrendelés" folder - ha explicit mappát kértek VAGY INBOX-ot
            if ($folder === 'INBOX' || str_contains($folder, 'Tabló megrendelés')) {
                $orderFolders = ['!!!Tabló megrendelés', 'INBOX.!!!Tabló megrendelés', 'INBOX/!!!Tabló megrendelés'];
                foreach ($orderFolders as $orderFolder) {
                    try {
                        $this->syncFolder($client, $orderFolder, 'inbound', $days, $dryRun);
                        $this->info("  Megrendelés mappa szinkronizálva: {$orderFolder}");
                        break;
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }

            // Sent mappa szinkronizálás
            if ($includeSent) {
                // Próbáljuk meg a tipikus sent mappa neveket
                $sentFolders = ['INBOX.Sent', 'Sent', 'INBOX/Sent', 'Sent Items', 'Elküldött elemek'];
                foreach ($sentFolders as $sentFolder) {
                    try {
                        $this->syncFolder($client, $sentFolder, 'outbound', $days, $dryRun);
                        break; // Ha sikerült, kilépünk
                    } catch (\Exception $e) {
                        continue; // Próbáljuk a következőt
                    }
                }
            }

            $client->disconnect();

            $this->newLine();
            $this->info('Szinkronizálás befejezve!');
            $this->table(
                ['Metrika', 'Érték'],
                [
                    ['Új emailek', $this->newCount],
                    ['Kihagyott (már létezik)', $this->skippedCount],
                    ['Projekthez kapcsolt', $this->linkedCount],
                ]
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Hiba történt: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    private function syncFolder($client, string $folderName, string $direction, int $days, bool $dryRun): void
    {
        $this->info("Mappa szinkronizálása: {$folderName} ({$direction})");

        try {
            $folder = $client->getFolder($folderName);
            if (! $folder) {
                $this->warn("  Mappa nem található: {$folderName}");

                return;
            }
        } catch (\Exception $e) {
            $this->warn("  Nem sikerült megnyitni: {$folderName} - ".$e->getMessage());

            return;
        }

        $since = now()->subDays($days)->format('d-M-Y');

        $messages = $folder->messages()
            ->since($since)
            ->setFetchBody(true)
            ->setFetchFlags(true)
            ->get();

        $this->info("  Talált üzenetek: ".$messages->count());

        $bar = $this->output->createProgressBar($messages->count());
        $bar->start();

        foreach ($messages as $message) {
            $this->processMessage($message, $direction, $folderName, $dryRun);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function processMessage($message, string $direction, string $folder, bool $dryRun): void
    {
        $messageId = $message->getMessageId()?->toString();

        if (! $messageId) {
            // Generáljunk egy egyedi ID-t ha nincs
            $messageId = md5($message->getSubject().$message->getDate()->toString());
        }

        // Már létezik?
        if (ProjectEmail::where('message_id', $messageId)->exists()) {
            $this->skippedCount++;

            return;
        }

        // Email adatok kinyerése
        $from = $message->getFrom()->first();
        $to = $message->getTo()->first();

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
            'attachments' => $this->extractAttachments($message),
            'imap_uid' => $message->getUid(),
            'imap_folder' => $folder,
            'email_date' => $message->getDate()?->toDate(),
        ];

        // Projekt automatikus hozzárendelése email cím alapján
        $projectId = $this->findProjectByEmail($data['from_email'], $data['to_email'], $data['subject']);
        if ($projectId) {
            $data['tablo_project_id'] = $projectId;
            $this->linkedCount++;
        }

        if (! $dryRun) {
            $email = ProjectEmail::create($data);

            // Ha megrendelés mappából jön és van PDF csatolmánya, indítsuk el az elemzést
            if ($this->isOrderFolder($folder) && $this->hasPdfAttachment($data['attachments'])) {
                ProcessOrderEmailJob::dispatch($email->id);
                $this->info("  → PDF megrendelőlap feldolgozás elindítva: {$email->id}");
            }
        }

        $this->newCount++;
    }

    private function extractThreadId($message): ?string
    {
        // Használjuk az In-Reply-To vagy References headert a thread azonosításhoz
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
        if (! $cc || $cc->count() === 0) {
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
        if (! $attachments || $attachments->count() === 0) {
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

    /**
     * MIME encoded header dekódolása (pl. =?UTF-8?Q?Emma_Vill=C3=A1nyi?=)
     */
    private function decodeMimeHeader(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Ha MIME encoded
        if (str_contains($value, '=?')) {
            $decoded = iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

            // Ha az iconv nem működik, próbáljuk mb_decode_mimeheader-rel
            if ($decoded === false || $decoded === $value) {
                $decoded = mb_decode_mimeheader($value);
            }

            return $decoded ?: $value;
        }

        return $value;
    }

    /**
     * Projekt keresése email cím vagy tárgy alapján
     */
    private function findProjectByEmail(string $fromEmail, string $toEmail, string $subject): ?int
    {
        // 1. Keresés a TabloContact táblában (kapcsolattartók email címei alapján)
        $contact = TabloContact::where('email', $fromEmail)
            ->orWhere('email', $toEmail)
            ->first();

        if ($contact && $contact->tablo_project_id) {
            return $contact->tablo_project_id;
        }

        // 2. Keresés projekt ID alapján a tárgyban (pl. "[PRJ-123]" vagy "Projekt #123")
        if (preg_match('/\[?PRJ[#-]?(\d+)\]?/i', $subject, $matches)) {
            $project = TabloProject::find($matches[1]);
            if ($project) {
                return $project->id;
            }
        }

        // 3. Keresés external_id alapján a tárgyban
        if (preg_match('/\[?EXT[#-]?(\d+)\]?/i', $subject, $matches)) {
            $project = TabloProject::where('external_id', $matches[1])->first();
            if ($project) {
                return $project->id;
            }
        }

        return null;
    }

    /**
     * Utolsó szinkronizálás státuszának megjelenítése
     */
    private function showStatus(): int
    {
        $lastSync = Cache::get('email_sync:last_sync');
        $isLocked = Cache::has('email_sync:lock');

        $this->info('=== Email Szinkronizálás Státusz ===');
        $this->newLine();

        if ($lastSync) {
            $lastSyncTime = \Carbon\Carbon::parse($lastSync);
            $this->info("Utolsó sikeres szinkronizálás: {$lastSyncTime->format('Y-m-d H:i:s')}");
            $this->info("                              ({$lastSyncTime->diffForHumans()})");
        } else {
            $this->warn('Még nem volt sikeres szinkronizálás.');
        }

        $this->newLine();

        if ($isLocked) {
            $this->warn('Státusz: FUTÁS ALATT (locked)');
        } else {
            $this->info('Státusz: Várakozik');
        }

        $this->newLine();

        // Email statisztikák
        $totalEmails = ProjectEmail::count();
        $linkedEmails = ProjectEmail::whereNotNull('tablo_project_id')->count();
        $unlinkedEmails = $totalEmails - $linkedEmails;
        $todayEmails = ProjectEmail::whereDate('created_at', today())->count();

        $this->table(
            ['Metrika', 'Érték'],
            [
                ['Összes email', $totalEmails],
                ['Projekthez kapcsolt', $linkedEmails],
                ['Kapcsolat nélküli', $unlinkedEmails],
                ['Mai új email', $todayEmails],
            ]
        );

        $this->newLine();
        $this->comment('Scheduler: 3 percenként fut automatikusan');
        $this->comment('Manual: php artisan emails:sync --sent');
        $this->comment('Queue:  php artisan emails:sync --dispatch');

        return Command::SUCCESS;
    }

    /**
     * Megrendelés mappából érkezett-e az email
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
     * Van-e PDF csatolmány
     */
    private function hasPdfAttachment(?array $attachments): bool
    {
        if (! $attachments) {
            return false;
        }

        foreach ($attachments as $attachment) {
            $name = $attachment['name'] ?? $attachment['filename'] ?? '';
            // MIME encoded nevek dekódolása
            if (str_contains($name, '=?')) {
                $name = iconv_mime_decode($name, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') ?: $name;
            }
            if (str_ends_with(strtolower($name), '.pdf')) {
                return true;
            }
        }

        return false;
    }
}

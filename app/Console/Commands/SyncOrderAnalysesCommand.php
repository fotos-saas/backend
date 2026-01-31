<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOrderEmailJob;
use App\Models\ProjectEmail;
use App\Models\TabloOrderAnalysis;
use Illuminate\Console\Command;
use Webklex\IMAP\Facades\Client;

class SyncOrderAnalysesCommand extends Command
{
    protected $signature = 'tablo:sync-orders
                            {--since=2025-09-01 : Ettől a dátumtól kezdve}
                            {--dry-run : Csak kiírja mit csinálna}
                            {--sync : Új leveleket is letölt a mappából}';

    protected $description = 'Tabló megrendelések szinkronizálása és elemzése a "!!!Tabló megrendelés" mappából';

    private array $orderFolders = [
        '!!!Tabló megrendelés',
        'INBOX.!!!Tabló megrendelés',
        'INBOX/!!!Tabló megrendelés',
    ];

    public function handle(): int
    {
        $since = $this->option('since');
        $dryRun = $this->option('dry-run');
        $doSync = $this->option('sync');

        $this->info("Tabló megrendelések szinkronizálása {$since} óta...");

        if ($dryRun) {
            $this->warn('DRY-RUN mód - nem történik tényleges művelet!');
        }

        // 1. Ha --sync, akkor először letöltjük az új leveleket
        if ($doSync) {
            $this->syncOrderFolder($since, $dryRun);
        }

        // 2. Keressük meg az összes PDF-es emailt ami nincs még elemezve
        $this->processUnanalyzedEmails($since, $dryRun);

        return Command::SUCCESS;
    }

    private function syncOrderFolder(string $since, bool $dryRun): void
    {
        $this->info('Levelek letöltése a megrendelés mappából...');

        try {
            $client = Client::account('default');
            $client->connect();

            $folder = null;
            foreach ($this->orderFolders as $folderName) {
                try {
                    $folder = $client->getFolder($folderName);
                    if ($folder) {
                        $this->info("  Mappa megtalálva: {$folderName}");
                        break;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            if (!$folder) {
                $this->error('Nem található a megrendelés mappa!');
                return;
            }

            $sinceDate = \Carbon\Carbon::parse($since);
            $messages = $folder->query()
                ->since($sinceDate)
                ->get();

            $newCount = 0;
            $skippedCount = 0;

            foreach ($messages as $message) {
                $messageId = $message->getMessageId()?->toString() ?? $message->getUid();

                // Már létezik?
                if (ProjectEmail::where('message_id', $messageId)->exists()) {
                    $skippedCount++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("  [DRY] Új email: " . $message->getSubject());
                    $newCount++;
                    continue;
                }

                // Email mentése
                $projectEmail = ProjectEmail::create([
                    'message_id' => $messageId,
                    'from_email' => $message->getFrom()[0]?->mail ?? '',
                    'from_name' => $message->getFrom()[0]?->personal ?? '',
                    'to_email' => $message->getTo()[0]?->mail ?? '',
                    'to_name' => $message->getTo()[0]?->personal ?? '',
                    'subject' => $message->getSubject()?->toString() ?? '',
                    'body_text' => $message->getTextBody() ?? '',
                    'body_html' => $message->getHTMLBody() ?? '',
                    'direction' => 'inbound',
                    'email_date' => $message->getDate()?->toDate(),
                    'imap_uid' => $message->getUid(),
                    'imap_folder' => $folder->path,
                    'attachments' => $this->getAttachments($message),
                ]);

                $newCount++;
                $this->line("  Új email: " . $message->getSubject());
            }

            $this->info("  Letöltve: {$newCount} új, {$skippedCount} kihagyott");

        } catch (\Exception $e) {
            $this->error('IMAP hiba: ' . $e->getMessage());
        }
    }

    private function getAttachments($message): array
    {
        $attachments = [];
        foreach ($message->getAttachments() as $attachment) {
            $attachments[] = [
                'name' => $attachment->getName(),
                'size' => $attachment->getSize(),
                'mime_type' => $attachment->getMimeType(),
            ];
        }
        return $attachments;
    }

    private function processUnanalyzedEmails(string $since, bool $dryRun): void
    {
        $this->info('Nem elemzett megrendelések keresése...');

        $sinceDate = \Carbon\Carbon::parse($since);

        // PDF csatolmányos emailek a "!!!Tabló megrendelés" mappából
        // amik nincsenek még KÉSZ elemezve
        // Keresés: mappa neve tartalmazza a "Tabl" szót (kezeli az UTF-7 kódolást is)
        $emails = ProjectEmail::where('email_date', '>=', $sinceDate)
            ->whereRaw("attachments::text LIKE '%pdf%'")
            ->where(function ($query) {
                // Keresés különböző formátumokban (plain és UTF-7 kódolt)
                $query->where('imap_folder', 'LIKE', '%!!!Tabló%')
                    ->orWhere('imap_folder', 'LIKE', '%!!!Tabl%')
                    ->orWhere('imap_folder', 'LIKE', '%megrendel%');
            })
            ->where(function ($query) {
                $query->whereDoesntHave('orderAnalysis')
                    ->orWhereHas('orderAnalysis', function ($q) {
                        $q->whereIn('status', ['pending', 'failed']);
                    });
            })
            ->orderBy('email_date', 'desc')
            ->get();

        $this->info("Találtam {$emails->count()} nem elemzett megrendelést.");

        if ($emails->isEmpty()) {
            $this->info('Nincs feldolgozandó megrendelés.');
            return;
        }

        $bar = $this->output->createProgressBar($emails->count());
        $bar->start();

        $dispatched = 0;
        foreach ($emails as $email) {
            if ($dryRun) {
                $this->line("\n  [DRY] Elemzésre: {$email->subject}");
            } else {
                // Job dispatch - a job maga hozza létre az analysis-t ha kell
                ProcessOrderEmailJob::dispatch($email->id);
                $dispatched++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if (!$dryRun) {
            $this->info("Elindítva: {$dispatched} elemzés a queue-ban.");
            $this->info('A feldolgozás a háttérben fut.');
        }
    }
}

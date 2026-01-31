<?php

namespace App\Console\Commands;

use App\Models\TabloContact;
use App\Models\TabloProject;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Webklex\IMAP\Facades\Client;

class CheckAwareness extends Command
{
    protected $signature = 'tablo:check-awareness
                            {--month=2025-09 : Melyik hónapot vizsgálja (YYYY-MM formátum)}
                            {--fix : Automatikusan bejelöli is_aware-t}
                            {--folder=INBOX : Melyik mappát vizsgálja}
                            {--sent : Elküldött emaileket is vizsgálja}';

    protected $description = 'Ellenőrzi a levelezéseket és kilistázza akikkel volt kommunikáció de nincs is_aware bejelölve';

    private Collection $emailsFromImap;

    public function handle(): int
    {
        $month = $this->option('month');
        $folder = $this->option('folder');
        $includeSent = $this->option('sent');
        $fix = $this->option('fix');

        // Hónap validálás
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error('Hibás hónap formátum! Használj YYYY-MM formátumot (pl. 2025-09)');

            return Command::FAILURE;
        }

        [$year, $monthNum] = explode('-', $month);
        $startDate = \Carbon\Carbon::createFromDate($year, $monthNum, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $this->info("Tablókirály email levelezés ellenőrzése: {$startDate->format('Y. F')}");
        $this->newLine();

        try {
            // 1. IMAP-ból emailek beolvasása
            $this->emailsFromImap = $this->fetchEmailsFromImap($folder, $includeSent, $startDate, $endDate);

            if ($this->emailsFromImap->isEmpty()) {
                $this->warn('Nem található email a megadott időszakban.');

                return Command::SUCCESS;
            }

            $this->info("Összesen {$this->emailsFromImap->count()} email találat az IMAP-ból.");
            $this->newLine();

            // 2. Egyedi email címek kinyerése (csak külső címek, nem a tablókirály)
            $externalEmails = $this->extractExternalEmails();

            $this->info("Egyedi külső email címek száma: {$externalEmails->count()}");
            $this->newLine();

            // 3. Összehasonlítás projektekkel
            $results = $this->checkAgainstProjects($externalEmails);

            // 4. Report generálása
            $this->generateReport($results, $fix);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Hiba történt: ' . $e->getMessage());
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    private function fetchEmailsFromImap(string $folder, bool $includeSent, \Carbon\Carbon $startDate, \Carbon\Carbon $endDate): Collection
    {
        $emails = collect();

        $client = Client::account('default');
        $client->connect();

        $this->info('Sikeresen csatlakozva az IMAP szerverhez.');

        // INBOX
        $emails = $emails->merge($this->fetchFromFolder($client, $folder, $startDate, $endDate));

        // Sent
        if ($includeSent) {
            $sentFolders = ['INBOX.Sent', 'Sent', 'INBOX/Sent', 'Sent Items', 'Elküldött elemek'];
            foreach ($sentFolders as $sentFolder) {
                try {
                    $emails = $emails->merge($this->fetchFromFolder($client, $sentFolder, $startDate, $endDate));
                    break;
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        $client->disconnect();

        return $emails;
    }

    private function fetchFromFolder($client, string $folderName, \Carbon\Carbon $startDate, \Carbon\Carbon $endDate): Collection
    {
        $emails = collect();

        try {
            $folder = $client->getFolder($folderName);
            if (! $folder) {
                return $emails;
            }
        } catch (\Exception $e) {
            return $emails;
        }

        $this->info("Mappa olvasása: {$folderName}");

        $messages = $folder->messages()
            ->since($startDate->format('d-M-Y'))
            ->before($endDate->addDay()->format('d-M-Y'))
            ->setFetchBody(false)
            ->setFetchFlags(false)
            ->get();

        $this->info("  Talált: {$messages->count()} üzenet");

        foreach ($messages as $message) {
            $from = $message->getFrom()->first();
            $to = $message->getTo()->first();
            $date = $message->getDate()?->toDate();

            // Csak a megadott hónapban lévő emailek
            if ($date && $date >= $startDate && $date <= $endDate) {
                $emails->push([
                    'from_email' => strtolower(trim($from?->mail ?? '')),
                    'from_name' => $this->decodeMimeHeader($from?->personal),
                    'to_email' => strtolower(trim($to?->mail ?? '')),
                    'to_name' => $this->decodeMimeHeader($to?->personal),
                    'subject' => $this->decodeMimeHeader($message->getSubject()?->toString()) ?? '(Nincs tárgy)',
                    'date' => $date,
                    'folder' => $folderName,
                ]);
            }
        }

        return $emails;
    }

    private function extractExternalEmails(): Collection
    {
        $ourDomains = ['tablokiraly.hu', 'tablokiralyok.hu'];

        $externalEmails = collect();

        foreach ($this->emailsFromImap as $email) {
            // From email
            $fromEmail = $email['from_email'];
            if ($fromEmail && ! $this->isOurDomain($fromEmail, $ourDomains)) {
                $externalEmails->put($fromEmail, [
                    'email' => $fromEmail,
                    'name' => $email['from_name'] ?? $fromEmail,
                    'last_subject' => $email['subject'],
                    'last_date' => $email['date'],
                    'direction' => 'inbound',
                ]);
            }

            // To email
            $toEmail = $email['to_email'];
            if ($toEmail && ! $this->isOurDomain($toEmail, $ourDomains)) {
                if (! $externalEmails->has($toEmail)) {
                    $externalEmails->put($toEmail, [
                        'email' => $toEmail,
                        'name' => $email['to_name'] ?? $toEmail,
                        'last_subject' => $email['subject'],
                        'last_date' => $email['date'],
                        'direction' => 'outbound',
                    ]);
                }
            }
        }

        return $externalEmails->values();
    }

    private function isOurDomain(string $email, array $ourDomains): bool
    {
        foreach ($ourDomains as $domain) {
            if (str_ends_with($email, '@' . $domain)) {
                return true;
            }
        }

        return false;
    }

    private function checkAgainstProjects(Collection $externalEmails): array
    {
        $results = [
            'should_be_aware' => collect(), // Van projekt, volt levelezés, de is_aware=false
            'no_project' => collect(),       // Volt levelezés, de nincs projekt
            'already_aware' => collect(),    // Van projekt, is_aware=true (OK)
        ];

        foreach ($externalEmails as $emailData) {
            $email = $emailData['email'];

            // Keresés a TabloContact táblában
            $contact = TabloContact::where('email', $email)->first();

            if ($contact && $contact->tablo_project_id) {
                $project = $contact->project;

                if ($project) {
                    if ($project->is_aware) {
                        $results['already_aware']->push([
                            'email' => $email,
                            'name' => $emailData['name'],
                            'project_id' => $project->id,
                            'project_name' => $project->display_name,
                            'last_subject' => $emailData['last_subject'],
                            'last_date' => $emailData['last_date'],
                        ]);
                    } else {
                        $results['should_be_aware']->push([
                            'email' => $email,
                            'name' => $emailData['name'],
                            'project_id' => $project->id,
                            'project_name' => $project->display_name,
                            'project' => $project,
                            'last_subject' => $emailData['last_subject'],
                            'last_date' => $emailData['last_date'],
                        ]);
                    }
                }
            } else {
                $results['no_project']->push([
                    'email' => $email,
                    'name' => $emailData['name'],
                    'last_subject' => $emailData['last_subject'],
                    'last_date' => $emailData['last_date'],
                ]);
            }
        }

        return $results;
    }

    private function generateReport(array $results, bool $fix): void
    {
        // 1. Akikkel volt levelezés de is_aware=false
        $shouldBeAware = $results['should_be_aware'];

        if ($shouldBeAware->isNotEmpty()) {
            $this->newLine();
            $this->warn('=== TUDNAK RÓLA - BE KELL JELÖLNI (' . $shouldBeAware->count() . ' db) ===');
            $this->newLine();

            $tableData = $shouldBeAware->map(fn ($item) => [
                $item['project_id'],
                \Illuminate\Support\Str::limit($item['project_name'], 40),
                $item['email'],
                $item['name'] ?? '-',
                \Illuminate\Support\Str::limit($item['last_subject'], 30),
                $item['last_date']?->format('Y-m-d') ?? '-',
            ])->toArray();

            $this->table(
                ['ID', 'Projekt', 'Email', 'Név', 'Utolsó tárgy', 'Dátum'],
                $tableData
            );

            if ($fix) {
                $this->newLine();
                $this->info('Automatikus bejelölés...');

                $updatedCount = 0;
                foreach ($shouldBeAware as $item) {
                    $item['project']->update(['is_aware' => true]);
                    $updatedCount++;
                }

                $this->info("✓ {$updatedCount} projekt is_aware bejelölve!");
            } else {
                $this->newLine();
                $this->comment('Használd a --fix opciót az automatikus bejelöléshez.');
            }
        } else {
            $this->info('✓ Nincs olyan projekt ahol be kellene jelölni az is_aware-t.');
        }

        // 2. Levelezés projekt nélkül (informatív)
        $noProject = $results['no_project'];

        if ($noProject->isNotEmpty()) {
            $this->newLine();
            $this->warn('=== LEVELEZÉS PROJEKT NÉLKÜL (' . $noProject->count() . ' db) ===');
            $this->comment('Ezekhez az email címekhez nincs TabloContact/TabloProject hozzárendelve.');
            $this->newLine();

            $tableData = $noProject->map(fn ($item) => [
                $item['email'],
                $item['name'] ?? '-',
                \Illuminate\Support\Str::limit($item['last_subject'], 50),
                $item['last_date']?->format('Y-m-d') ?? '-',
            ])->toArray();

            $this->table(
                ['Email', 'Név', 'Utolsó tárgy', 'Dátum'],
                $tableData
            );
        }

        // 3. Összesítés
        $this->newLine();
        $this->info('=== ÖSSZESÍTÉS ===');
        $this->table(
            ['Kategória', 'Darab'],
            [
                ['Már be van jelölve (is_aware=true)', $results['already_aware']->count()],
                ['BE KELL JELÖLNI (is_aware=false)', $shouldBeAware->count()],
                ['Projekt nélküli levelezés', $noProject->count()],
            ]
        );
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
}

<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOrderEmailJob;
use App\Models\ProjectEmail;
use App\Models\TabloOrderAnalysis;
use Illuminate\Console\Command;

class ProcessExistingOrderEmailsCommand extends Command
{
    protected $signature = 'tablo:process-order-emails
                            {--dry-run : Csak megjeleníti, mit dolgozna fel}
                            {--force : Újra feldolgozza a már elemzett emaileket is}';

    protected $description = 'Visszamenőleg feldolgozza a megrendelőlapos emaileket (PDF csatolmánnyal)';

    public function handle(): int
    {
        $this->info('Megrendelőlapos emailek keresése...');

        // Keressük azokat az emaileket, amik:
        // 1. A "!!!Tabló megrendelés" mappából jöttek VAGY a tárgyuk/címzettje erre utal
        // 2. Van PDF csatolmányuk
        $query = ProjectEmail::query()
            ->where(function ($q) {
                // Mappa alapján (imap_folder mező)
                $q->where('imap_folder', 'LIKE', '%Tabló megrendelés%')
                    ->orWhere('imap_folder', 'LIKE', '%megrendeles%')
                    // Vagy a tárgy/from alapján
                    ->orWhere('subject', 'LIKE', '%megrendelőlap%')
                    ->orWhere('subject', 'LIKE', '%megrendelés%')
                    ->orWhere('subject', 'LIKE', '%rendelőlap%');
            })
            ->whereNotNull('attachments')
            ->whereRaw("attachments::text != '[]'");

        // Ha nincs --force, akkor csak azokat, amiknek nincs még elemzésük
        if (! $this->option('force')) {
            $query->whereDoesntHave('orderAnalysis');
        }

        $emails = $query->get();

        // Szűrjük azokra, amiknek van PDF csatolmányuk
        $emailsWithPdf = $emails->filter(function ($email) {
            $attachments = is_array($email->attachments) ? $email->attachments : json_decode($email->attachments, true);
            if (! $attachments) {
                return false;
            }

            foreach ($attachments as $attachment) {
                $name = $attachment['name'] ?? $attachment['filename'] ?? '';
                if (str_ends_with(strtolower($name), '.pdf')) {
                    return true;
                }
            }

            return false;
        });

        $count = $emailsWithPdf->count();

        if ($count === 0) {
            $this->info('Nincs feldolgozandó email.');

            return self::SUCCESS;
        }

        $this->info("Találtam {$count} feldolgozandó emailt.");

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Tárgy', 'Feladó', 'Dátum', 'Mappa', 'Van elemzés?'],
                $emailsWithPdf->map(fn ($email) => [
                    $email->id,
                    \Illuminate\Support\Str::limit($email->subject, 50),
                    $email->from_email,
                    $email->email_date?->format('Y-m-d'),
                    $email->imap_folder,
                    $email->orderAnalysis ? 'Igen' : 'Nem',
                ])
            );

            $this->warn('Dry-run mód - nem történt feldolgozás.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $processed = 0;
        $skipped = 0;

        foreach ($emailsWithPdf as $email) {
            // Ha már van elemzése és nem force, akkor ugorjuk
            if (! $this->option('force') && $email->orderAnalysis()->exists()) {
                $skipped++;
                $bar->advance();

                continue;
            }

            // Ha force és van elemzése, töröljük az előzőt
            if ($this->option('force') && $email->orderAnalysis()->exists()) {
                $email->orderAnalysis()->delete();
            }

            // Hozzuk létre a pending elemzést
            TabloOrderAnalysis::create([
                'project_email_id' => $email->id,
                'tablo_project_id' => $email->tablo_project_id,
                'status' => 'pending',
            ]);

            // Indítsuk el a feldolgozó jobot
            ProcessOrderEmailJob::dispatch($email->id);

            $processed++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Feldolgozás elindítva: {$processed} email");
        if ($skipped > 0) {
            $this->warn("Átugorva (már van elemzés): {$skipped} email");
        }

        $this->info('A jobok a háttérben futnak. Ellenőrizd a queue-t!');

        return self::SUCCESS;
    }
}

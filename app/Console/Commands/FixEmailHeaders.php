<?php

namespace App\Console\Commands;

use App\Models\ProjectEmail;
use Illuminate\Console\Command;

class FixEmailHeaders extends Command
{
    protected $signature = 'emails:fix-headers';

    protected $description = 'MIME encoded email headerek dekódolása (from_name, to_name, subject)';

    public function handle(): int
    {
        $this->info('MIME encoded headerek javítása...');

        $emails = ProjectEmail::all();
        $fixedCount = 0;

        $bar = $this->output->createProgressBar($emails->count());
        $bar->start();

        foreach ($emails as $email) {
            $updated = false;

            // Subject javítása
            if ($email->subject && str_contains($email->subject, '=?')) {
                $email->subject = $this->decodeMimeHeader($email->subject);
                $updated = true;
            }

            // From name javítása
            if ($email->from_name && str_contains($email->from_name, '=?')) {
                $email->from_name = $this->decodeMimeHeader($email->from_name);
                $updated = true;
            }

            // To name javítása
            if ($email->to_name && str_contains($email->to_name, '=?')) {
                $email->to_name = $this->decodeMimeHeader($email->to_name);
                $updated = true;
            }

            if ($updated) {
                $email->save();
                $fixedCount++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Javítva: {$fixedCount} email");

        return Command::SUCCESS;
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

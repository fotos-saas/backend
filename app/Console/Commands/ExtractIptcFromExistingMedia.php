<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * IPTC adatok kinyerése a meglévő képekből
 *
 * Végigmegy az összes PartnerAlbum médiafájlon és kinyeri az IPTC title-t.
 */
class ExtractIptcFromExistingMedia extends Command
{
    protected $signature = 'media:extract-iptc {--dry-run : Csak szimuláció, nem ment}';
    protected $description = 'IPTC title kinyerése a meglévő PartnerAlbum képekből';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('IPTC adatok kinyerése...');
        if ($dryRun) {
            $this->warn('DRY RUN mód - nem történik mentés!');
        }

        // Csak PartnerAlbum-hoz tartozó médiák
        $media = Media::where('model_type', 'App\\Models\\PartnerAlbum')
            ->whereNull('custom_properties->iptc_title')
            ->get();

        $this->info("Összesen {$media->count()} média feldolgozása...");

        $updated = 0;
        $skipped = 0;
        $noIptc = 0;

        $bar = $this->output->createProgressBar($media->count());
        $bar->start();

        foreach ($media as $item) {
            $path = $item->getPath();

            if (!file_exists($path)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $iptcTitle = $this->extractIptcTitle($path);

            if ($iptcTitle) {
                if (!$dryRun) {
                    $item->setCustomProperty('iptc_title', $iptcTitle);
                    $item->save();
                }
                $updated++;

                if ($this->output->isVerbose()) {
                    $this->newLine();
                    $this->line("  {$item->file_name} => {$iptcTitle}");
                }
            } else {
                $noIptc++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Eredmény:");
        $this->line("  - Frissítve: {$updated}");
        $this->line("  - Nincs IPTC: {$noIptc}");
        $this->line("  - Kihagyva (fájl nem létezik): {$skipped}");

        if ($dryRun && $updated > 0) {
            $this->newLine();
            $this->warn("Futtasd --dry-run nélkül a tényleges mentéshez!");
        }

        return Command::SUCCESS;
    }

    /**
     * IPTC title kinyerése a képből
     */
    private function extractIptcTitle(string $filePath): ?string
    {
        $size = @getimagesize($filePath, $info);

        if ($size === false || !isset($info['APP13'])) {
            return null;
        }

        $iptc = @iptcparse($info['APP13']);

        if (!$iptc) {
            return null;
        }

        // Prioritás: Title (2#005) > Caption (2#120) > Headline (2#105)
        $titleFields = ['2#005', '2#120', '2#105'];

        foreach ($titleFields as $field) {
            if (isset($iptc[$field][0]) && !empty(trim($iptc[$field][0]))) {
                return trim($iptc[$field][0]);
            }
        }

        return null;
    }
}

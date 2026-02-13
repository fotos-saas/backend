<?php

namespace App\Console\Commands;

use App\Models\Photo;
use App\Models\Setting;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\Conversions\FileManipulator;

class ReapplyWatermarksCommand extends Command
{
    protected $signature = 'watermark:reapply
                            {--album= : Csak egy adott album fotóit vízjelezi újra}
                            {--partner= : Egy partner összes fotójának újravízjelezése}
                            {--dry-run : Csak számol, nem módosít}';

    protected $description = 'Meglévő fotók preview konverziójának újragenerálása az új vízjel stílussal';

    public function handle(FileManipulator $fileManipulator): int
    {
        $watermarkEnabled = Setting::get('watermark_enabled', true);
        if (! $watermarkEnabled) {
            $this->error('A vízjelezés ki van kapcsolva a beállításokban.');

            return self::FAILURE;
        }

        $query = Photo::query()->whereHas('media');

        if ($albumId = $this->option('album')) {
            $query->where('album_id', (int) $albumId);
            $this->info("Album szűrés: #{$albumId}");
        }

        if ($partnerId = $this->option('partner')) {
            $query->whereHas('album', function ($q) use ($partnerId) {
                $q->whereHas('createdBy', function ($q2) use ($partnerId) {
                    $q2->where('tablo_partner_id', (int) $partnerId);
                });
            });
            $this->info("Partner szűrés: #{$partnerId}");
        }

        $totalCount = $query->count();
        $this->info("Összesen {$totalCount} fotó található.");

        if ($this->option('dry-run')) {
            $this->info('[DRY RUN] Nem történik módosítás.');

            return self::SUCCESS;
        }

        if ($totalCount === 0) {
            $this->info('Nincs feldolgozandó fotó.');

            return self::SUCCESS;
        }

        $this->info('Preview konverziók újragenerálása és vízjelezés...');
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $processed = 0;
        $skipped = 0;
        $errors = 0;

        $query->with('media')
            ->chunkById(100, function ($photos) use ($fileManipulator, &$processed, &$skipped, &$errors, $bar) {
                foreach ($photos as $photo) {
                    try {
                        $media = $photo->getFirstMedia('photo');
                        if (! $media) {
                            $skipped++;
                            $bar->advance();
                            continue;
                        }

                        // Clear old watermarked custom property
                        if ($media->getCustomProperty('watermarked')) {
                            $media->forgetCustomProperty('watermarked');
                            $media->save();
                        }

                        // Regenerate preview conversion using Spatie's FileManipulator
                        // This recreates preview from original, then the event listener
                        // (ApplyWatermarkToPreview) applies the new tiled watermark automatically.
                        $fileManipulator->createDerivedFiles($media, ['preview']);

                        $processed++;
                    } catch (\Exception $e) {
                        $errors++;
                        $this->newLine();
                        $this->error("Hiba (photo #{$photo->id}): {$e->getMessage()}");
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->info("Kész! Feldolgozva: {$processed}, Kihagyva: {$skipped}, Hiba: {$errors}");

        return self::SUCCESS;
    }
}

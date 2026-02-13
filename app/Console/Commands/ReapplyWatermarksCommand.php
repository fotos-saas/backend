<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ReapplyWatermarksCommand extends Command
{
    protected $signature = 'watermark:reapply
                            {--model= : Csak egy adott model típus (pl. TabloGallery)}
                            {--dry-run : Csak számol, nem módosít}';

    protected $description = 'Meglévő fotók preview konverziójának újragenerálása az új vízjel stílussal';

    private const ALLOWED_MODELS = [
        'App\\Models\\Photo',
        'App\\Models\\PartnerAlbum',
        'App\\Models\\TabloProject',
        'App\\Models\\TabloGallery',
    ];

    public function handle(FileManipulator $fileManipulator): int
    {
        $watermarkEnabled = Setting::get('watermark_enabled', true);
        if (! $watermarkEnabled) {
            $this->error('A vízjelezés ki van kapcsolva a beállításokban.');

            return self::FAILURE;
        }

        $query = Media::query()
            ->whereIn('model_type', self::ALLOWED_MODELS)
            ->whereJsonContains('generated_conversions->preview', true);

        if ($modelFilter = $this->option('model')) {
            $fullClass = "App\\Models\\{$modelFilter}";
            if (! in_array($fullClass, self::ALLOWED_MODELS)) {
                $this->error("Nem támogatott model: {$modelFilter}");

                return self::FAILURE;
            }
            $query->where('model_type', $fullClass);
            $this->info("Model szűrés: {$modelFilter}");
        }

        $totalCount = $query->count();
        $this->info("Összesen {$totalCount} média preview konverzióval.");

        if ($this->option('dry-run')) {
            $this->info('[DRY RUN] Nem történik módosítás.');

            return self::SUCCESS;
        }

        if ($totalCount === 0) {
            $this->info('Nincs feldolgozandó média.');

            return self::SUCCESS;
        }

        $this->info('Preview konverziók újragenerálása és vízjelezés...');
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $processed = 0;
        $skipped = 0;
        $errors = 0;

        $query->chunkById(50, function ($mediaItems) use ($fileManipulator, &$processed, &$skipped, &$errors, $bar) {
            foreach ($mediaItems as $media) {
                try {
                    $fileManipulator->createDerivedFiles($media, ['preview']);
                    $processed++;
                } catch (\Exception $e) {
                    $errors++;
                    $this->newLine();
                    $this->error("Hiba (media #{$media->id}): {$e->getMessage()}");
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Kész! Feldolgozva: {$processed}, Hiba: {$errors}");

        return self::SUCCESS;
    }
}

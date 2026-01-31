<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Migrate existing Resources to use BaseResource.
 *
 * This command automatically updates all Filament Resources to:
 * 1. Extend BaseResource instead of Resource
 * 2. Remove the HasGranularPermissions trait (now in BaseResource)
 * 3. Update imports
 */
class MigrateToBaseResourceCommand extends Command
{
    protected $signature = 'filament:migrate-to-base-resource
        {--dry-run : Preview changes without applying}
        {--file= : Migrate only a specific file}';

    protected $description = 'Migrate Filament Resources to use BaseResource';

    protected int $filesModified = 0;

    protected int $filesSkipped = 0;

    public function handle(): int
    {
        $this->info('🔄 Migrating Resources to BaseResource...');

        $dryRun = $this->option('dry-run');
        $specificFile = $this->option('file');

        $resourcePath = app_path('Filament/Resources');

        if ($specificFile) {
            $files = [new \SplFileInfo($specificFile)];
        } else {
            $files = File::allFiles($resourcePath);
        }

        foreach ($files as $file) {
            $this->processFile($file, $dryRun);
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("✅ DRY-RUN: {$this->filesModified} file(s) would be modified, {$this->filesSkipped} skipped");
        } else {
            $this->info("✅ {$this->filesModified} file(s) migrated, {$this->filesSkipped} skipped");
        }

        return 0;
    }

    protected function processFile(\SplFileInfo $file, bool $dryRun): void
    {
        $path = $file->getPathname();
        $relativePath = str_replace(app_path('Filament/Resources/').'/', '', $path);

        // Skip non-PHP files
        if ($file->getExtension() !== 'php') {
            return;
        }

        // Skip Pages and RelationManagers
        if (str_contains($path, '/Pages/') || str_contains($path, '/RelationManagers/')) {
            return;
        }

        // Skip BaseResource itself
        if (str_contains($path, 'BaseResource.php')) {
            return;
        }

        $content = File::get($path);

        // Check if it's a Resource file
        if (! str_contains($content, 'extends Resource') && ! str_contains($content, 'extends BaseResource')) {
            return;
        }

        // Already migrated
        if (str_contains($content, 'extends BaseResource')) {
            $this->comment("⏭️  Already migrated: {$relativePath}");
            $this->filesSkipped++;

            return;
        }

        $this->line("📝 Processing: {$relativePath}");

        $newContent = $this->migrateContent($content);

        if ($content === $newContent) {
            $this->comment("   No changes needed");
            $this->filesSkipped++;

            return;
        }

        if (! $dryRun) {
            File::put($path, $newContent);
        }

        $this->info('   ✓ Migrated to BaseResource'.($dryRun ? ' (would be)' : ''));
        $this->filesModified++;
    }

    protected function migrateContent(string $content): string
    {
        // Check if file is in a subdirectory (namespace contains more than App\Filament\Resources)
        $isSubdirectory = preg_match('/namespace App\\\\Filament\\\\Resources\\\\[A-Za-z]+;/', $content);

        // 1. Remove HasGranularPermissions import
        $content = preg_replace(
            '/use App\\\\Filament\\\\Concerns\\\\HasGranularPermissions;\n/',
            '',
            $content
        );

        // 2. Remove "use HasGranularPermissions;" trait usage
        $content = preg_replace(
            '/\s+use HasGranularPermissions;\n/',
            "\n",
            $content
        );

        // 3. Handle Resource import based on directory structure
        if ($isSubdirectory) {
            // Replace Resource import with BaseResource import
            $content = preg_replace(
                '/use Filament\\\\Resources\\\\Resource;\n/',
                "use App\\Filament\\Resources\\BaseResource;\n",
                $content
            );
        } else {
            // Remove Resource import (BaseResource is in same namespace)
            $content = preg_replace(
                '/use Filament\\\\Resources\\\\Resource;\n/',
                '',
                $content
            );
        }

        // 4. Change "extends Resource" to "extends BaseResource"
        $content = preg_replace(
            '/extends Resource(\s|\n)/',
            'extends BaseResource$1',
            $content
        );

        return $content;
    }
}

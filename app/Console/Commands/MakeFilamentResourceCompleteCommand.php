<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Complete Filament Resource Generator.
 *
 * Creates a fully functional Filament Resource with:
 * - Model (optional)
 * - Migration (optional)
 * - Policy with permission keys (optional)
 * - Resource extending BaseResource
 * - Navigation properties (icon, group, sort, labels)
 * - Config entry for permissions
 * - Permissions sync
 *
 * Usage:
 *   php artisan make:filament-resource-complete Product --group="E-commerce" --generate
 *   php artisan make:filament-resource-complete TabloContact --group="Tabló" --with-model --with-policy
 */
class MakeFilamentResourceCompleteCommand extends Command
{
    protected $signature = 'make:filament-resource-complete
        {name : Resource neve (pl. Product, TabloContact)}
        {--model= : Model neve (default: Resource névből)}
        {--group= : Navigation group (pl. "Tabló", "E-commerce")}
        {--icon=heroicon-o-rectangle-stack : Navigation icon}
        {--label= : Navigation label (magyar, default: pluralizált név)}
        {--singular= : Singular label (magyar, default: név)}
        {--sort=10 : Navigation sort order}
        {--generate : Generálja a CRUD formot és táblát a model alapján}
        {--soft-deletes : SoftDeletes támogatás}
        {--view : View page generálása}
        {--with-model : Model létrehozása is (ha nem létezik)}
        {--with-migration : Migration létrehozása (--with-model esetén)}
        {--with-policy : Policy létrehozása permission key-ekkel}
        {--no-pages : Ne generáljon Pages-t}
        {--no-config : Ne adja hozzá a config-hoz}
        {--no-sync : Ne futtassa a permissions:sync-et}
        {--dry-run : Csak előnézet, nem ír fájlokat}';

    protected $description = 'Teljes Filament Resource létrehozása: Model, Policy, Resource, Config, Permissions - mindent egy lépésben';

    protected string $resourceName;

    protected string $modelName;

    protected string $permissionKey;

    protected string $label;

    protected string $singularLabel;

    protected string $group;

    protected string $icon;

    protected int $sort;

    public function handle(): int
    {
        $this->resourceName = $this->argument('name');
        $this->modelName = $this->option('model') ?: $this->resourceName;
        $this->permissionKey = $this->generatePermissionKey($this->resourceName);
        $this->label = $this->option('label') ?: $this->generateLabel($this->resourceName);
        $this->singularLabel = $this->option('singular') ?: $this->generateSingularLabel($this->resourceName);
        $this->group = $this->option('group') ?: '';
        $this->icon = $this->option('icon');
        $this->sort = (int) $this->option('sort');

        $dryRun = $this->option('dry-run');

        $this->newLine();
        $this->info("🚀 Filament Resource teljes generálás: {$this->resourceName}");
        $this->newLine();

        if ($dryRun) {
            $this->warn('⚠️  DRY-RUN mód - nem történik tényleges változás');
            $this->newLine();
        }

        $this->showSummary();

        if (! $dryRun && ! $this->confirm('Folytatod a generálást?', true)) {
            $this->info('Megszakítva.');

            return 0;
        }

        $steps = $this->getSteps();
        $totalSteps = count($steps);
        $currentStep = 0;

        foreach ($steps as $stepName => $stepMethod) {
            $currentStep++;
            $this->line("📦 {$currentStep}/{$totalSteps}: {$stepName}...");

            if (! $dryRun) {
                $result = $this->$stepMethod();
                if ($result === false) {
                    $this->error("❌ Hiba történt: {$stepName}");

                    return 1;
                }
            }

            $this->info('  ✓ Kész');
        }

        $this->newLine();
        $this->info("✅ A '{$this->permissionKey}' Resource használatra kész!");
        $this->newLine();

        $this->showCreatedFiles();

        return 0;
    }

    /**
     * Show summary before generation.
     */
    protected function showSummary(): void
    {
        $this->table(['Beállítás', 'Érték'], [
            ['Resource név', $this->resourceName.'Resource'],
            ['Model', $this->modelName],
            ['Permission Key', $this->permissionKey],
            ['Navigation Label', $this->label],
            ['Navigation Group', $this->group ?: '(nincs)'],
            ['Navigation Icon', $this->icon],
            ['Navigation Sort', $this->sort],
            ['Model létrehozás', $this->option('with-model') ? 'Igen' : 'Nem'],
            ['Migration', $this->option('with-migration') ? 'Igen' : 'Nem'],
            ['Policy', $this->option('with-policy') ? 'Igen' : 'Nem'],
            ['CRUD generálás', $this->option('generate') ? 'Igen' : 'Nem'],
        ]);
        $this->newLine();
    }

    /**
     * Get steps to execute.
     */
    protected function getSteps(): array
    {
        $steps = [];

        if ($this->option('with-model')) {
            $steps['Model generálás'] = 'stepCreateModel';
        }

        $steps['Resource generálás'] = 'stepCreateResource';
        $steps['BaseResource migrálás'] = 'stepMigrateToBaseResource';
        $steps['Navigation properties'] = 'stepSetNavigationProperties';
        $steps['Form Schema kiszervezés'] = 'stepCreateFormSchema';
        $steps['Edit Page testreszabás'] = 'stepCustomizeEditPage';

        if ($this->option('with-policy')) {
            $steps['Policy generálás'] = 'stepCreatePolicy';
        }

        if (! $this->option('no-config')) {
            $steps['Config frissítés'] = 'stepUpdateConfig';
        }

        if (! $this->option('no-sync')) {
            $steps['Permissions sync'] = 'stepSyncPermissions';
        }

        $steps['Cache tisztítás'] = 'stepClearCache';

        return $steps;
    }

    /**
     * Step: Create Model.
     */
    protected function stepCreateModel(): bool
    {
        $modelPath = app_path("Models/{$this->modelName}.php");

        if (file_exists($modelPath)) {
            $this->comment("    Model már létezik: {$this->modelName}");

            return true;
        }

        $args = ['name' => $this->modelName];

        if ($this->option('with-migration')) {
            $args['--migration'] = true;
        }

        $this->callSilently('make:model', $args);

        return file_exists($modelPath);
    }

    /**
     * Step: Create Resource using Filament's command.
     */
    protected function stepCreateResource(): bool
    {
        // Filament uses 'model' as the argument name, not 'name'
        $args = ['model' => $this->resourceName];

        if ($this->option('generate')) {
            $args['--generate'] = true;
        }

        if ($this->option('soft-deletes')) {
            $args['--soft-deletes'] = true;
        }

        if ($this->option('view')) {
            $args['--view'] = true;
        }

        $this->callSilently('make:filament-resource', $args);

        // Filament 4 creates nested structure: Resources/{ModelPlural}/{Model}Resource.php
        // We need to find where the resource was created
        $resourcePath = $this->findResourcePath();

        return $resourcePath !== null && file_exists($resourcePath);
    }

    /**
     * Find the generated resource path.
     * Filament 4 uses nested structure: Resources/{ModelPlural}/{Model}Resource.php
     */
    protected function findResourcePath(): ?string
    {
        // Try Filament 4 nested structure first
        $plural = Str::plural($this->resourceName);
        $nestedPath = app_path("Filament/Resources/{$plural}/{$this->resourceName}Resource.php");

        if (file_exists($nestedPath)) {
            return $nestedPath;
        }

        // Try flat structure (older Filament or manually created)
        $flatPath = app_path("Filament/Resources/{$this->resourceName}Resource.php");

        if (file_exists($flatPath)) {
            return $flatPath;
        }

        return null;
    }

    /**
     * Step: Migrate Resource to extend BaseResource.
     */
    protected function stepMigrateToBaseResource(): bool
    {
        $resourcePath = $this->findResourcePath();

        if (! $resourcePath || ! file_exists($resourcePath)) {
            return false;
        }

        $content = file_get_contents($resourcePath);

        // Replace import
        $content = str_replace(
            'use Filament\Resources\Resource;',
            'use App\Filament\Resources\BaseResource;',
            $content
        );

        // Replace extends
        $content = preg_replace(
            '/class\s+(\w+Resource)\s+extends\s+Resource/',
            'class $1 extends BaseResource',
            $content
        );

        file_put_contents($resourcePath, $content);

        return true;
    }

    /**
     * Step: Set navigation properties.
     */
    protected function stepSetNavigationProperties(): bool
    {
        $resourcePath = $this->findResourcePath();

        if (! $resourcePath || ! file_exists($resourcePath)) {
            return false;
        }

        $content = file_get_contents($resourcePath);

        // Add necessary imports if not present
        if (! str_contains($content, 'use BackedEnum;')) {
            $content = str_replace(
                "namespace App\\Filament\\Resources;\n",
                "namespace App\\Filament\\Resources;\n\nuse BackedEnum;",
                $content
            );
        }

        if (! str_contains($content, 'use UnitEnum;')) {
            $content = str_replace(
                "use BackedEnum;",
                "use BackedEnum;\nuse UnitEnum;",
                $content
            );
        }

        // Remove existing navigation properties that Filament may have generated
        // to avoid duplicates
        $content = preg_replace(
            '/\s*protected\s+static\s+[^\$]*\$navigationIcon\s*=\s*[^;]+;/m',
            '',
            $content
        );
        $content = preg_replace(
            '/\s*protected\s+static\s+[^\$]*\$navigationLabel\s*=\s*[^;]+;/m',
            '',
            $content
        );
        $content = preg_replace(
            '/\s*protected\s+static\s+[^\$]*\$navigationGroup\s*=\s*[^;]+;/m',
            '',
            $content
        );
        $content = preg_replace(
            '/\s*protected\s+static\s+[^\$]*\$navigationSort\s*=\s*[^;]+;/m',
            '',
            $content
        );
        $content = preg_replace(
            '/\s*protected\s+static\s+[^\$]*\$modelLabel\s*=\s*[^;]+;/m',
            '',
            $content
        );
        $content = preg_replace(
            '/\s*protected\s+static\s+[^\$]*\$pluralModelLabel\s*=\s*[^;]+;/m',
            '',
            $content
        );

        // Build navigation properties block
        $navigationCode = $this->buildNavigationPropertiesCode();

        // Find the position after "protected static ?string $model = "
        $pattern = '/(protected\s+static\s+\?string\s+\$model\s*=\s*[^;]+;)/';

        if (preg_match($pattern, $content)) {
            $content = preg_replace(
                $pattern,
                "$1\n\n{$navigationCode}",
                $content
            );
        }

        file_put_contents($resourcePath, $content);

        return true;
    }

    /**
     * Build navigation properties code block.
     */
    protected function buildNavigationPropertiesCode(): string
    {
        $code = [];

        // Icon
        $code[] = "    protected static BackedEnum|string|null \$navigationIcon = '{$this->icon}';";

        // Navigation label
        $code[] = "    protected static ?string \$navigationLabel = '{$this->label}';";

        // Model labels (hungarian)
        $code[] = "    protected static ?string \$modelLabel = '{$this->singularLabel}';";
        $code[] = "    protected static ?string \$pluralModelLabel = '{$this->label}';";

        // Navigation group
        if ($this->group) {
            $code[] = "    protected static string|UnitEnum|null \$navigationGroup = '{$this->group}';";
        }

        // Sort
        $code[] = "    protected static ?int \$navigationSort = {$this->sort};";

        return implode("\n\n", $code);
    }

    /**
     * Step: Create Form Schema in separate Schemas folder with Section wrapper.
     */
    protected function stepCreateFormSchema(): bool
    {
        $resourcePath = $this->findResourcePath();

        if (! $resourcePath || ! file_exists($resourcePath)) {
            return false;
        }

        // Determine the resource directory (Filament 4 nested structure)
        $resourceDir = dirname($resourcePath);
        $schemasDir = $resourceDir.'/Schemas';

        // Create Schemas directory if not exists
        if (! is_dir($schemasDir)) {
            mkdir($schemasDir, 0755, true);
        }

        // Read current resource to extract form components
        $resourceContent = file_get_contents($resourcePath);

        // Extract form components from the resource
        $formComponents = $this->extractFormComponents($resourceContent);

        // Generate Form Schema class
        $formSchemaContent = $this->generateFormSchemaContent($formComponents);
        $formSchemaPath = $schemasDir."/{$this->resourceName}Form.php";

        file_put_contents($formSchemaPath, $formSchemaContent);

        // Update the Resource to use the external Form Schema
        $this->updateResourceToUseFormSchema($resourcePath);

        return file_exists($formSchemaPath);
    }

    /**
     * Extract form components from resource content.
     */
    protected function extractFormComponents(string $content): string
    {
        // Try to find the form() method content
        // Pattern: public static function form(Schema $schema): Schema { return $schema->components([ ... ]); }
        if (preg_match('/->components\s*\(\s*\[(.*?)\]\s*\)/s', $content, $matches)) {
            return trim($matches[1]);
        }

        // Fallback: generate basic placeholder
        return "TextInput::make('name')\n                            ->label('Név')\n                            ->required()";
    }

    /**
     * Generate Form Schema class content with Section wrapper.
     */
    protected function generateFormSchemaContent(string $formComponents): string
    {
        $plural = Str::plural($this->resourceName);
        $namespace = "App\\Filament\\Resources\\{$plural}\\Schemas";

        // Indent form components properly for inside Section
        $indentedComponents = $this->indentComponents($formComponents, 24);

        return <<<PHP
<?php

namespace {$namespace};

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class {$this->resourceName}Form
{
    public static function configure(Schema \$schema): Schema
    {
        return \$schema
            ->components([
                Section::make('Alapadatok')
                    ->columns(2)
                    ->components([
{$indentedComponents}
                    ]),
            ]);
    }
}
PHP;
    }

    /**
     * Indent components to proper level.
     */
    protected function indentComponents(string $components, int $spaces): string
    {
        $indent = str_repeat(' ', $spaces);
        $lines = explode("\n", $components);
        $result = [];

        foreach ($lines as $line) {
            // Skip empty lines
            if (trim($line) === '') {
                $result[] = '';
                continue;
            }

            // Remove existing indentation and add new
            $trimmed = ltrim($line);
            $result[] = $indent.$trimmed;
        }

        return implode("\n", $result);
    }

    /**
     * Update Resource to use external Form Schema.
     */
    protected function updateResourceToUseFormSchema(string $resourcePath): void
    {
        $content = file_get_contents($resourcePath);
        $plural = Str::plural($this->resourceName);

        // Add import for the Form Schema class
        $formSchemaImport = "use App\\Filament\\Resources\\{$plural}\\Schemas\\{$this->resourceName}Form;";

        if (! str_contains($content, $formSchemaImport)) {
            // Add after namespace imports
            $content = preg_replace(
                '/(use [^;]+;\n)(\n*class\s)/',
                "$1{$formSchemaImport}\n$2",
                $content
            );
        }

        // Replace form() method to use external schema
        $newFormMethod = <<<PHP
    public static function form(Schema \$schema): Schema
    {
        return {$this->resourceName}Form::configure(\$schema);
    }
PHP;

        // Replace existing form method
        $content = preg_replace(
            '/public\s+static\s+function\s+form\s*\(\s*Schema\s+\$schema\s*\)\s*:\s*Schema\s*\{[^}]+\}/s',
            $newFormMethod,
            $content
        );

        file_put_contents($resourcePath, $content);
    }

    /**
     * Step: Customize Edit Page with "Vissza a listához" button.
     */
    protected function stepCustomizeEditPage(): bool
    {
        $plural = Str::plural($this->resourceName);
        $editPagePath = app_path("Filament/Resources/{$plural}/Pages/Edit{$this->resourceName}.php");

        if (! file_exists($editPagePath)) {
            $this->comment("    Edit page nem található: Edit{$this->resourceName}.php");

            return true;
        }

        $content = file_get_contents($editPagePath);

        // Skip if already customized
        if (str_contains($content, "Action::make('back')")) {
            $this->comment('    Edit page már testreszabva');

            return true;
        }

        // Add Action import if not present
        if (! str_contains($content, 'use Filament\Actions\Action;')) {
            $content = str_replace(
                'use Filament\Actions\DeleteAction;',
                "use Filament\Actions\Action;\nuse Filament\Actions\DeleteAction;",
                $content
            );
        }

        // Replace getHeaderActions method to include "Vissza a listához" button
        $newHeaderActionsMethod = <<<PHP
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Vissza a listához')
                ->url(fn () => {$this->resourceName}Resource::getUrl('index'))
                ->color('gray'),

            DeleteAction::make(),
        ];
    }
PHP;

        // Replace existing getHeaderActions method
        $content = preg_replace(
            '/protected\s+function\s+getHeaderActions\s*\(\s*\)\s*:\s*array\s*\{[^}]+\}/s',
            $newHeaderActionsMethod,
            $content
        );

        file_put_contents($editPagePath, $content);

        return true;
    }

    /**
     * Step: Create Policy.
     */
    protected function stepCreatePolicy(): bool
    {
        $policyPath = app_path("Policies/{$this->modelName}Policy.php");

        // Don't overwrite existing policy
        if (file_exists($policyPath)) {
            $this->comment("    Policy már létezik: {$this->modelName}Policy");

            return true;
        }

        $policyContent = $this->generatePolicyContent();

        // Ensure Policies directory exists
        if (! is_dir(app_path('Policies'))) {
            mkdir(app_path('Policies'), 0755, true);
        }

        file_put_contents($policyPath, $policyContent);

        return file_exists($policyPath);
    }

    /**
     * Generate policy content with permission keys.
     */
    protected function generatePolicyContent(): string
    {
        $modelClass = $this->modelName;
        $modelVar = Str::camel($this->modelName);
        $permKey = $this->permissionKey;

        return <<<PHP
<?php

namespace App\Policies;

use App\Models\\{$modelClass};
use App\Models\User;

/**
 * Policy for {$modelClass} model.
 *
 * Uses permission keys from filament-permissions.php config.
 * Permission key: {$permKey}
 */
class {$modelClass}Policy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User \$user): bool
    {
        return \$user->can('{$permKey}.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User \$user, {$modelClass} \${$modelVar}): bool
    {
        return \$user->can('{$permKey}.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User \$user): bool
    {
        return \$user->can('{$permKey}.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User \$user, {$modelClass} \${$modelVar}): bool
    {
        return \$user->can('{$permKey}.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User \$user, {$modelClass} \${$modelVar}): bool
    {
        return \$user->can('{$permKey}.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User \$user, {$modelClass} \${$modelVar}): bool
    {
        return \$user->can('{$permKey}.edit');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User \$user, {$modelClass} \${$modelVar}): bool
    {
        return \$user->can('{$permKey}.delete');
    }
}
PHP;
    }

    /**
     * Step: Update filament-permissions.php config.
     */
    protected function stepUpdateConfig(): bool
    {
        $configPath = config_path('filament-permissions.php');

        if (! file_exists($configPath)) {
            $this->error('Config fájl nem található: config/filament-permissions.php');

            return false;
        }

        $content = file_get_contents($configPath);

        // Check if permission key already exists
        if (str_contains($content, "'{$this->permissionKey}'")) {
            $this->comment("    Config bejegyzés már létezik: {$this->permissionKey}");

            return true;
        }

        // Build new config entry
        $configEntry = $this->buildConfigEntry();

        // Find the end of 'resources' array and insert before closing bracket
        // Looking for the pattern: 'resources' => [...],
        $pattern = "/('resources'\s*=>\s*\[)(.*?)(^\s{4}\],)/ms";

        if (preg_match($pattern, $content, $matches)) {
            $newContent = $matches[1].$matches[2]."\n".$configEntry."\n".$matches[3];
            $content = preg_replace($pattern, $newContent, $content);
            file_put_contents($configPath, $content);

            return true;
        }

        $this->warn('    Config frissítés nem sikerült automatikusan - manuálisan kell hozzáadni');

        return true; // Don't fail the whole process
    }

    /**
     * Build config entry for the resource.
     */
    protected function buildConfigEntry(): string
    {
        $indent = '        ';

        $entry = "{$indent}'{$this->permissionKey}' => [\n";
        $entry .= "{$indent}    'label' => '{$this->label}',\n";
        $entry .= "{$indent}    'permissions' => [\n";
        $entry .= "{$indent}        'view' => 'Megtekintés',\n";
        $entry .= "{$indent}        'create' => 'Létrehozás',\n";
        $entry .= "{$indent}        'edit' => 'Szerkesztés',\n";
        $entry .= "{$indent}        'delete' => 'Törlés',\n";
        $entry .= "{$indent}    ],\n";
        $entry .= "{$indent}],";

        return $entry;
    }

    /**
     * Step: Sync permissions.
     */
    protected function stepSyncPermissions(): bool
    {
        $this->callSilently('permissions:sync');

        return true;
    }

    /**
     * Step: Clear cache.
     */
    protected function stepClearCache(): bool
    {
        $this->callSilently('optimize:clear');

        return true;
    }

    /**
     * Show created files summary.
     */
    protected function showCreatedFiles(): void
    {
        $this->info('📍 Létrehozott/módosított fájlok:');

        if ($this->option('with-model')) {
            $this->line("   - app/Models/{$this->modelName}.php");
        }

        if ($this->option('with-migration')) {
            $this->line('   - database/migrations/xxx_create_'.Str::snake(Str::plural($this->modelName)).'_table.php');
        }

        if ($this->option('with-policy')) {
            $this->line("   - app/Policies/{$this->modelName}Policy.php");
        }

        $plural = Str::plural($this->resourceName);
        $this->line("   - app/Filament/Resources/{$plural}/{$this->resourceName}Resource.php");
        $this->line("   - app/Filament/Resources/{$plural}/Schemas/{$this->resourceName}Form.php");
        $this->line("   - app/Filament/Resources/{$plural}/Pages/List{$plural}.php");
        $this->line("   - app/Filament/Resources/{$plural}/Pages/Create{$this->resourceName}.php");
        $this->line("   - app/Filament/Resources/{$plural}/Pages/Edit{$this->resourceName}.php");

        if (! $this->option('no-config')) {
            $this->line('   - config/filament-permissions.php (frissítve)');
        }

        $this->newLine();
        $this->info("🔗 URL: /admin/{$this->permissionKey}");
    }

    /**
     * Generate permission key from resource name.
     * Same logic as ResourceDiscoveryService.
     */
    protected function generatePermissionKey(string $name): string
    {
        // Remove 'Resource' suffix if present
        $name = str_replace('Resource', '', $name);

        // Convert to kebab-case and pluralize
        $slug = Str::slug(Str::kebab($name));

        return Str::plural($slug);
    }

    /**
     * Generate navigation label (plural, hungarian style).
     */
    protected function generateLabel(string $name): string
    {
        // Remove 'Resource' suffix if present
        $name = str_replace('Resource', '', $name);

        // Convert CamelCase to spaced words
        return Str::headline($name);
    }

    /**
     * Generate singular label (hungarian style).
     */
    protected function generateSingularLabel(string $name): string
    {
        // Remove 'Resource' suffix if present
        $name = str_replace('Resource', '', $name);

        // Convert CamelCase to spaced words
        return Str::headline($name);
    }
}

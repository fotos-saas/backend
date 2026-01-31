<?php

namespace App\Services;

use Filament\Resources\Resource;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use Throwable;

/**
 * Automatic Resource Discovery Service
 *
 * This service automatically discovers all Filament Resources in the codebase
 * and generates permission configurations for them.
 *
 * Security Considerations:
 * - New Resources are "closed by default" - no access without explicit permissions
 * - Only discovers classes that extend Filament\Resources\Resource
 * - Excludes Page and RelationManager classes
 * - Config overrides take precedence over auto-discovered values
 *
 * @see \App\Console\Commands\PermissionsSyncCommand
 */
class ResourceDiscoveryService
{
    /**
     * Base namespace for Filament Resources.
     */
    protected string $resourceNamespace = 'App\\Filament\\Resources';

    /**
     * Base path for Filament Resources.
     */
    protected string $resourcePath;

    public function __construct()
    {
        $this->resourcePath = app_path('Filament/Resources');
    }

    /**
     * Discover all Filament Resources and generate permission configurations.
     *
     * @return array<string, array{
     *   class: string,
     *   label: string,
     *   permissions: array<string, string>,
     *   auto_discovered: bool
     * }>
     */
    public function discoverResources(): array
    {
        $resources = [];

        if (! File::isDirectory($this->resourcePath)) {
            return $resources;
        }

        $files = File::allFiles($this->resourcePath);

        foreach ($files as $file) {
            try {
                $class = $this->getClassFromFile($file);

                if (! $class || ! $this->isValidResource($class)) {
                    continue;
                }

                $key = $this->generatePermissionKey($class);

                $resources[$key] = [
                    'class' => $class,
                    'label' => $this->getResourceLabel($class),
                    'namespace' => $class,
                    'permissions' => $this->getDefaultPermissions(),
                    'auto_discovered' => true,
                ];
            } catch (Throwable $e) {
                // Skip files that cause errors (syntax errors, missing dependencies, etc.)
                continue;
            }
        }

        return $resources;
    }

    /**
     * Merge auto-discovered resources with manual config overrides.
     *
     * Manual overrides take precedence - they can:
     * - Add extra permissions (tabs, relations, actions)
     * - Override labels
     * - Disable auto-discovered resources
     *
     * @param  array  $discovered  Auto-discovered resources
     * @param  array  $overrides  Manual config overrides
     */
    public function mergeWithOverrides(array $discovered, array $overrides): array
    {
        $merged = $discovered;

        foreach ($overrides as $key => $override) {
            if (isset($merged[$key])) {
                // Merge override with discovered (override wins)
                $merged[$key] = array_merge($merged[$key], $override);
                $merged[$key]['auto_discovered'] = false;
            } else {
                // Override-only resource (not auto-discovered)
                $merged[$key] = array_merge([
                    'permissions' => $this->getDefaultPermissions(),
                    'auto_discovered' => false,
                ], $override);
            }
        }

        return $merged;
    }

    /**
     * Get the fully qualified class name from a file.
     */
    protected function getClassFromFile($file): ?string
    {
        $relativePath = $file->getRelativePathname();

        // Skip Pages, RelationManagers, and non-PHP files
        if (
            str_contains($relativePath, '/Pages/') ||
            str_contains($relativePath, '/RelationManagers/') ||
            ! str_ends_with($relativePath, '.php')
        ) {
            return null;
        }

        // Build the full class name
        $class = $this->resourceNamespace.'\\'.
            str_replace(['/', '.php'], ['\\', ''], $relativePath);

        return class_exists($class) ? $class : null;
    }

    /**
     * Check if a class is a valid Filament Resource.
     */
    protected function isValidResource(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($class);

            // Must extend Resource
            if (! $reflection->isSubclassOf(Resource::class)) {
                return false;
            }

            // Must not be abstract
            if ($reflection->isAbstract()) {
                return false;
            }

            // Skip BaseResource itself
            if ($class === 'App\\Filament\\Resources\\BaseResource') {
                return false;
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Generate a permission key from the class name.
     *
     * Examples:
     * - TabloStatusResource -> tablo-statuses
     * - UserResource -> users
     * - WorkSessionResource -> work-sessions
     */
    protected function generatePermissionKey(string $class): string
    {
        $className = class_basename($class);
        $resourceName = str_replace('Resource', '', $className);

        // Convert to kebab-case and pluralize
        $slug = Str::slug(Str::kebab($resourceName));

        return Str::plural($slug);
    }

    /**
     * Get the resource label from the class.
     */
    protected function getResourceLabel(string $class): string
    {
        try {
            if (method_exists($class, 'getPluralModelLabel')) {
                return $class::getPluralModelLabel();
            }
        } catch (Throwable) {
            // Fall back to class name
        }

        $className = class_basename($class);
        $resourceName = str_replace('Resource', '', $className);

        return Str::headline($resourceName);
    }

    /**
     * Get default CRUD permissions.
     */
    protected function getDefaultPermissions(): array
    {
        return [
            'view' => 'Megtekintés',
            'create' => 'Létrehozás',
            'edit' => 'Szerkesztés',
            'delete' => 'Törlés',
        ];
    }

    /**
     * List all discovered resources for debugging.
     *
     * @return array<string, array{class: string, label: string}>
     */
    public function listResources(): array
    {
        $resources = $this->discoverResources();

        return array_map(fn ($r) => [
            'class' => $r['class'],
            'label' => $r['label'],
        ], $resources);
    }
}

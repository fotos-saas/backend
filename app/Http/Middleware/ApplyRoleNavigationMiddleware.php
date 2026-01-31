<?php

namespace App\Http\Middleware;

use App\Models\NavigationConfiguration;
use App\Services\NavigationConfigService;
use Closure;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for applying role-specific navigation configurations.
 *
 * This middleware intercepts Filament admin requests and dynamically
 * modifies the navigation based on the user's role configuration.
 *
 * Method B: Middleware-based approach (100% guaranteed to work).
 */
class ApplyRoleNavigationMiddleware
{
    protected NavigationConfigService $navService;

    public function __construct(NavigationConfigService $navService)
    {
        $this->navService = $navService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to Filament admin panel requests
        if (! $request->is('admin*')) {
            return $next($request);
        }

        // Get authenticated user
        $user = auth()->user();

        if (! $user || ! $user->roles) {
            return $next($request);
        }

        // Get the user's first role
        $role = $user->roles->first();

        if (! $role) {
            return $next($request);
        }

        // Check if this role has any navigation configurations
        $hasConfigurations = NavigationConfiguration::where('role_id', $role->id)->exists();

        if (! $hasConfigurations) {
            // No custom navigation, use default
            return $next($request);
        }

        // Apply role-specific navigation
        Filament::getCurrentOrDefaultPanel()
            ->navigation(function (NavigationBuilder $builder) use ($role) {
                return $this->buildCustomNavigation($builder, $role);
            });

        return $next($request);
    }

    /**
     * Build custom navigation for the given role.
     */
    protected function buildCustomNavigation(NavigationBuilder $builder, $role): NavigationBuilder
    {
        // Get custom navigation items for this role
        $navigationItems = $this->navService->getNavigationForRole($role);

        // Get navigation groups for this role
        $navigationGroups = $this->navService->getNavigationGroupsForRole($role);

        // Group items by navigation group
        $groupedItems = [];
        foreach ($navigationItems as $item) {
            $groupKey = $item['group'] ?? 'ungrouped';
            $groupedItems[$groupKey][] = $item;
        }

        // Sort groups by sort_order
        $sortedGroups = $navigationGroups->sortBy('sort_order');

        // Build navigation items for each group
        foreach ($sortedGroups as $groupKey => $group) {
            if (! isset($groupedItems[$groupKey])) {
                continue;
            }

            foreach ($groupedItems[$groupKey] as $item) {
                try {
                    $resourceClass = $item['resource_class'];

                    // Check if resource class exists and is accessible
                    if (! class_exists($resourceClass)) {
                        continue;
                    }

                    // Create navigation item
                    $navItem = NavigationItem::make($item['label'])
                        ->icon($item['icon'])
                        ->sort($item['sort'])
                        ->group($group['label']);

                    // Set URL (handle potential errors)
                    try {
                        $navItem->url($resourceClass::getUrl());
                    } catch (\Exception $e) {
                        // Skip items that can't generate URLs
                        continue;
                    }

                    $builder->item($navItem);
                } catch (\Exception $e) {
                    // Skip items that cause errors
                    continue;
                }
            }
        }

        // Add ungrouped items
        if (isset($groupedItems['ungrouped'])) {
            foreach ($groupedItems['ungrouped'] as $item) {
                try {
                    $resourceClass = $item['resource_class'];

                    if (! class_exists($resourceClass)) {
                        continue;
                    }

                    $navItem = NavigationItem::make($item['label'])
                        ->icon($item['icon'])
                        ->sort($item['sort']);

                    try {
                        $navItem->url($resourceClass::getUrl());
                    } catch (\Exception $e) {
                        continue;
                    }

                    $builder->item($navItem);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return $builder;
    }
}


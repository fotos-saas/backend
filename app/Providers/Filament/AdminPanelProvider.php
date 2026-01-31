<?php

namespace App\Providers\Filament;

use App\Services\BrandingService;
use App\Services\NavigationConfigService;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(fn (BrandingService $branding): string => $branding->getName())
            ->brandLogo(fn (BrandingService $branding): ?string => $branding->getLogoUrl())
            ->colors([
                'primary' => Color::Amber,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->navigationGroups([
                NavigationGroup::make('Platform Beállítások')
                    ->collapsed(),
                NavigationGroup::make('Szállítás és Fizetés')
                    ->collapsed(),
                NavigationGroup::make('Email Rendszer')
                    ->collapsed(),
            ])
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                return $this->buildCustomNavigation($builder);
            })
            ->renderHook(
                'panels::body.end',
                fn (): string => '<script src="'.asset('js/lightbox-navigation.js').'"></script>',
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Build custom navigation based on role-specific configuration.
     */
    protected function buildCustomNavigation(NavigationBuilder $builder): NavigationBuilder
    {
        $user = auth()->user();

        if (! $user) {
            return $builder;
        }

        // Get user's role
        $role = $user->roles()->first();

        if (! $role) {
            return $builder;
        }

        // Get navigation configuration for this role
        $navService = app(NavigationConfigService::class);
        $navigationItems = $navService->getNavigationForRole($role);

        // Get navigation groups for this role to get labels
        $navigationGroups = $navService->getNavigationGroupsForRole($role);

        // Group items by navigation group
        $grouped = collect($navigationItems)->groupBy('group');

        foreach ($grouped as $groupKey => $items) {
            $groupItems = [];

            foreach ($items as $item) {
                if (! $item['visible']) {
                    continue;
                }

                // Check if user has permission to access this resource
                if (! $this->canAccessResource($item['resource_class'])) {
                    continue;
                }

                $groupItems[] = NavigationItem::make($item['label'])
                    ->url($item['url'])
                    ->icon($item['icon'])
                    ->sort($item['sort']);
            }

            if (! empty($groupItems)) {
                if ($groupKey === 'ungrouped' || ! $groupKey) {
                    // Add all ungrouped items at once
                    $builder->items($groupItems);
                } else {
                    // Get the group label from the navigation groups
                    $groupLabel = $navigationGroups->get($groupKey)?->label ?? $groupKey;

                    // Add items in a group
                    $builder->group(
                        NavigationGroup::make($groupLabel)
                            ->items($groupItems)
                    );
                }
            }
        }

        return $builder;
    }

    /**
     * Check if the user can access the given resource class.
     */
    protected function canAccessResource(string $resourceClass): bool
    {
        if (! class_exists($resourceClass)) {
            return false;
        }

        // Check if the resource has canViewAny method
        if (method_exists($resourceClass, 'canViewAny')) {
            return $resourceClass::canViewAny();
        }

        return true;
    }
}

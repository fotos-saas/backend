<?php

namespace App\Filament\Schemas;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;

/**
 * Reusable Filament form section for admin preview functionality.
 *
 * Provides:
 * - Admin preview button that opens frontend in new tab
 * - Preview token is generated on-the-fly (5 min expiry)
 * - Full access rights but with "Admin előnézet" indicator
 */
class AdminPreviewSection
{
    /**
     * Create admin preview section for Filament forms.
     */
    public static function make(): Section
    {
        return Section::make('Admin előnézet')
            ->description('Teljes jogú előnézet admin számára')
            ->schema([
                \Filament\Forms\Components\Placeholder::make('admin_preview_info')
                    ->label('')
                    ->content(new HtmlString(
                        '<div class="text-sm text-gray-600 dark:text-gray-400">' .
                        '<p class="mb-2"><strong>Admin előnézet:</strong> Teljes hozzáférés a frontend oldalhoz, de a felületen jelezve van, hogy admin előnézetben vagy.</p>' .
                        '<p class="text-xs text-gray-500">A link 5 percig érvényes és egyszeri használatra szól.</p>' .
                        '</div>'
                    )),

                Actions::make([
                    Action::make('openAdminPreview')
                        ->label('Megnyitás admin előnézetben')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->action(function ($record, $livewire) {
                            // Generate fresh token on button click
                            $url = $record->getAdminPreviewUrl();

                            Notification::make()
                                ->title('Admin előnézet megnyitva')
                                ->body('A link 5 percig érvényes.')
                                ->success()
                                ->send();

                            // Dispatch JavaScript event to open URL in new tab
                            $livewire->dispatch('open-url-in-new-tab', url: $url);
                        })
                        ->visible(fn ($record) => $record !== null),
                ]),

                // Hidden element to handle the JavaScript event
                \Filament\Forms\Components\Placeholder::make('preview_js_handler')
                    ->label('')
                    ->content(new HtmlString(
                        '<div x-data x-on:open-url-in-new-tab.window="window.open($event.detail.url, \'_blank\')"></div>'
                    ))
                    ->extraAttributes(['class' => 'hidden']),
            ]);
    }
}

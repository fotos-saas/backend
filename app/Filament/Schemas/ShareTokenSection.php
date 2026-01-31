<?php

namespace App\Filament\Schemas;

use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;

/**
 * Reusable Filament form section for share token settings.
 *
 * Provides:
 * - Share token enable/disable toggle
 * - Share token display with copy button
 * - Expiration date picker
 * - Share URL with copy button
 */
class ShareTokenSection
{
    /**
     * Create share token section for Filament forms.
     *
     * @param  string  $modelClass  Model class (e.g., \App\Models\TabloProject::class)
     */
    public static function make(string $modelClass): Section
    {
        return Section::make('Megosztási link')
            ->description('Kód nélküli megosztás beállítása')
            ->schema([
                Forms\Components\Toggle::make('share_token_enabled')
                    ->label('Megosztási link engedélyezése')
                    ->helperText('Token automatikusan generálódik bekapcsoláskor')
                    ->default(true)
                    ->live()
                    ->afterStateUpdated(function ($state, $set, $get) use ($modelClass) {
                        if ($state && empty($get('share_token'))) {
                            $instance = new $modelClass;
                            $set('share_token', $instance->generateShareToken());
                        }
                    }),

                Forms\Components\DateTimePicker::make('share_token_expires_at')
                    ->label('Lejárati idő')
                    ->visible(fn ($get) => $get('share_token_enabled'))
                    ->helperText('Üresen hagyva végtelen érvényességű'),

                Forms\Components\Placeholder::make('share_url_preview')
                    ->label('Megosztási URL')
                    ->visible(fn ($get, $record) => $get('share_token_enabled') && $record?->share_token)
                    ->content(function ($record) {
                        if (! $record || ! $record->share_token) {
                            return 'Mentés után jelenik meg';
                        }

                        $url = $record->getShareUrl();

                        return new HtmlString(
                            '<div class="flex items-center gap-2">' .
                            '<code class="text-xs bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded break-all">' . e($url) . '</code>' .
                            '<button type="button" onclick="navigator.clipboard.writeText(\'' . e($url) . '\').then(() => { ' .
                            'const btn = this; btn.innerHTML = \'✓\'; setTimeout(() => btn.innerHTML = \'📋\', 1500); ' .
                            '})" class="text-lg hover:scale-110 transition-transform" title="Másolás">📋</button>' .
                            '</div>'
                        );
                    }),

                Actions::make([
                    Action::make('regenerateShareToken')
                        ->label('Új link generálása')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Link újragenerálás')
                        ->modalDescription('A régi megosztási link érvénytelenné válik. Biztosan újragenerálod?')
                        ->action(function ($record, $set) {
                            $record->share_token = $record->generateShareToken();
                            $record->save();
                            $set('share_token', $record->share_token);
                            Notification::make()
                                ->title('Megosztási link újragenerálva')
                                ->success()
                                ->send();
                        })
                        ->visible(fn ($record) => $record?->share_token),
                ])->visible(fn ($get, $record) => $get('share_token_enabled') && $record?->share_token),
            ])
            ->columns(2);
    }
}

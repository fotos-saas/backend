<?php

namespace App\Filament\Resources\TabloProjectResource\RelationManagers;

use App\Filament\Resources\TabloProjectResource;
use App\Models\TabloGuestSession;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * GuestSessionsRelationManager
 *
 * Vendég session-ök kezelése a TabloProject szerkesztő oldalán.
 * Ban/unban funkció, aktivitás monitoring.
 */
class GuestSessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'guestSessions';

    protected static ?string $title = 'Vendégek';

    protected static ?string $modelLabel = 'Vendég';

    protected static ?string $pluralModelLabel = 'Vendégek';

    protected static \BackedEnum|string|null $icon = 'heroicon-o-users';

    /**
     * Determine if the relation manager can be viewed for the given record.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return TabloProjectResource::canAccessRelation('guest-sessions');
    }

    /**
     * Badge showing count of guests.
     */
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->guestSessions()->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * Badge color - info for guests.
     */
    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'info';
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('guest_name')
                    ->label('Név')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('guest_email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Nincs megadva')
                    ->copyable(),

                Tables\Columns\ToggleColumn::make('is_banned')
                    ->label('Tiltott')
                    ->onColor('danger')
                    ->offColor('success')
                    ->onIcon('heroicon-o-no-symbol')
                    ->offIcon('heroicon-o-check-circle'),

                Tables\Columns\TextColumn::make('votes_count')
                    ->label('Szavazatok')
                    ->counts('votes')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state > 0
                        ? new HtmlString('<span style="background: #3b82f6; color: white; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600;">' . $state . '</span>')
                        : new HtmlString('<span style="color: #9ca3af;">0</span>')
                    ),

                Tables\Columns\TextColumn::make('posts_count')
                    ->label('Hozzászólások')
                    ->counts('posts')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state > 0
                        ? new HtmlString('<span style="background: #8b5cf6; color: white; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600;">' . $state . '</span>')
                        : new HtmlString('<span style="color: #9ca3af;">0</span>')
                    ),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP cím')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label('Utolsó aktivitás')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->description(fn (TabloGuestSession $record) => $record->last_activity_at
                        ? $record->last_activity_at->diffForHumans()
                        : 'Nincs aktivitás'
                    ),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Regisztrált')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_activity_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_banned')
                    ->label('Tiltott')
                    ->placeholder('Mind')
                    ->trueLabel('Csak tiltottak')
                    ->falseLabel('Csak aktívak'),
            ])
            ->headerActions([
                // Nincs header action - vendégeket nem lehet manuálisan létrehozni
            ])
            ->actions([
                Action::make('viewActivity')
                    ->label('Megtekintés')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (TabloGuestSession $record) => $record->guest_name . ' - Aktivitás')
                    ->modalWidth('2xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Bezárás')
                    ->modalContent(function (TabloGuestSession $record) {
                        $votes = $record->votes()->with('option')->latest()->take(10)->get();
                        $posts = $record->posts()->with('discussion')->latest()->take(10)->get();

                        $html = '<div style="padding: 16px;">';

                        // Szavazatok
                        $html .= '<h3 style="font-weight: 600; margin-bottom: 12px;">Szavazatok (' . $record->votes()->count() . ')</h3>';
                        if ($votes->isEmpty()) {
                            $html .= '<p style="color: #6b7280;">Nincs szavazat.</p>';
                        } else {
                            $html .= '<ul style="list-style: disc; padding-left: 20px; margin-bottom: 24px;">';
                            foreach ($votes as $vote) {
                                $optionLabel = $vote->option?->label ?? 'Törölt opció';
                                $html .= '<li style="margin-bottom: 4px;">';
                                $html .= '<span style="font-weight: 500;">' . e($optionLabel) . '</span>';
                                $html .= ' <span style="color: #6b7280; font-size: 12px;">' . $vote->voted_at->format('Y-m-d H:i') . '</span>';
                                $html .= '</li>';
                            }
                            $html .= '</ul>';
                        }

                        // Hozzászólások
                        $html .= '<h3 style="font-weight: 600; margin-bottom: 12px;">Hozzászólások (' . $record->posts()->count() . ')</h3>';
                        if ($posts->isEmpty()) {
                            $html .= '<p style="color: #6b7280;">Nincs hozzászólás.</p>';
                        } else {
                            foreach ($posts as $post) {
                                $discussionTitle = $post->discussion?->title ?? 'Törölt beszélgetés';
                                $contentPreview = mb_substr(strip_tags($post->content), 0, 100);
                                if (mb_strlen(strip_tags($post->content)) > 100) {
                                    $contentPreview .= '...';
                                }

                                $html .= '<div style="background: #f3f4f6; padding: 12px; border-radius: 8px; margin-bottom: 8px;">';
                                $html .= '<div style="font-weight: 500; margin-bottom: 4px;">' . e($discussionTitle) . '</div>';
                                $html .= '<div style="font-size: 13px; color: #374151;">' . e($contentPreview) . '</div>';
                                $html .= '<div style="font-size: 12px; color: #6b7280; margin-top: 4px;">' . $post->created_at->format('Y-m-d H:i') . '</div>';
                                $html .= '</div>';
                            }
                        }

                        $html .= '</div>';

                        return new HtmlString($html);
                    }),

                DeleteAction::make()
                    ->label('Törlés')
                    ->requiresConfirmation()
                    ->modalHeading('Vendég törlése')
                    ->modalDescription('Biztosan törölni szeretnéd ezt a vendéget? A szavazatai és hozzászólásai is törlődnek.')
                    ->modalSubmitActionLabel('Törlés'),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    BulkAction::make('banSelected')
                        ->label('Kijelöltek tiltása')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Vendégek tiltása')
                        ->modalDescription('A kijelölt vendégek nem fognak tudni szavazni vagy hozzászólni.')
                        ->action(function (Collection $records) {
                            $records->each(function (TabloGuestSession $record) {
                                $record->is_banned = true;
                                $record->save();
                            });
                        }),

                    BulkAction::make('unbanSelected')
                        ->label('Kijelöltek tiltásának feloldása')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Tiltások feloldása')
                        ->modalDescription('A kijelölt vendégek ismét hozzáférhetnek a projekthez.')
                        ->action(function (Collection $records) {
                            $records->each(function (TabloGuestSession $record) {
                                $record->is_banned = false;
                                $record->save();
                            });
                        }),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

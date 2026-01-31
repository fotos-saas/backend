<?php

namespace App\Filament\Resources\TabloProjectResource\RelationManagers;

use App\Filament\Resources\TabloProjectResource;
use App\Models\TabloPoll;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * PollsRelationManager
 *
 * Szavazások kezelése a TabloProject szerkesztő oldalán.
 */
class PollsRelationManager extends RelationManager
{
    protected static string $relationship = 'polls';

    protected static ?string $title = 'Szavazások';

    protected static ?string $modelLabel = 'Szavazás';

    protected static ?string $pluralModelLabel = 'Szavazások';

    protected static \BackedEnum|string|null $icon = 'heroicon-o-chart-bar';

    /**
     * Determine if the relation manager can be viewed for the given record.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return TabloProjectResource::canAccessRelation('polls');
    }

    /**
     * Badge showing count of active polls.
     */
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->polls()->where('is_active', true)->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * Badge color - success for active polls.
     */
    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'success';
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Cím')
                    ->searchable()
                    ->sortable()
                    ->description(fn (TabloPoll $record) => $record->description
                        ? mb_substr($record->description, 0, 50) . (mb_strlen($record->description) > 50 ? '...' : '')
                        : null
                    ),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Típus')
                    ->formatStateUsing(fn (string $state) => $state === 'template' ? 'Sablon' : 'Egyedi')
                    ->colors([
                        'primary' => 'template',
                        'secondary' => 'custom',
                    ]),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktív')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->action(
                        Action::make('toggleActive')
                            ->label(fn (TabloPoll $record) => $record->is_active ? 'Lezárás' : 'Aktiválás')
                            ->icon(fn (TabloPoll $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                            ->color(fn (TabloPoll $record) => $record->is_active ? 'danger' : 'success')
                            ->requiresConfirmation()
                            ->action(function (TabloPoll $record) {
                                $record->is_active = ! $record->is_active;
                                $record->save();
                            })
                    ),

                Tables\Columns\IconColumn::make('is_multiple_choice')
                    ->label('Több választ')
                    ->boolean()
                    ->trueIcon('heroicon-o-check')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('info')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('options_count')
                    ->label('Opciók')
                    ->counts('options')
                    ->sortable(),

                Tables\Columns\TextColumn::make('votes_count')
                    ->label('Szavazatok')
                    ->counts('votes')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state > 0
                        ? new HtmlString('<span style="background: #10b981; color: white; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600;">' . $state . '</span>')
                        : new HtmlString('<span style="color: #9ca3af;">0</span>')
                    ),

                Tables\Columns\TextColumn::make('close_at')
                    ->label('Zárás')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('Nincs határidő'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Új szavazás')
                    ->form([
                        Forms\Components\TextInput::make('title')
                            ->label('Cím')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label('Leírás')
                            ->rows(3),

                        Forms\Components\Select::make('type')
                            ->label('Típus')
                            ->options([
                                'template' => 'Sablon választás',
                                'custom' => 'Egyedi szavazás',
                            ])
                            ->default('custom')
                            ->required(),

                        Forms\Components\Toggle::make('is_multiple_choice')
                            ->label('Több opció választható')
                            ->default(false)
                            ->reactive(),

                        Forms\Components\TextInput::make('max_votes_per_guest')
                            ->label('Maximum szavazat/vendég')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(10)
                            ->visible(fn (callable $get) => $get('is_multiple_choice')),

                        Forms\Components\Toggle::make('show_results_before_vote')
                            ->label('Eredmények láthatók szavazás előtt')
                            ->default(false),

                        Forms\Components\Toggle::make('use_for_finalization')
                            ->label('Véglegesítéshez használt')
                            ->helperText('A nyertes sablon automatikusan beállításra kerül')
                            ->default(false),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktív')
                            ->default(true),

                        Forms\Components\DateTimePicker::make('close_at')
                            ->label('Automatikus lezárás')
                            ->helperText('Ha üres, nincs automatikus lezárás'),
                    ]),
            ])
            ->actions([
                Action::make('viewResults')
                    ->label('Eredmények')
                    ->icon('heroicon-o-chart-bar')
                    ->modalHeading(fn (TabloPoll $record) => $record->title . ' - Eredmények')
                    ->modalWidth('2xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Bezárás')
                    ->modalContent(function (TabloPoll $record) {
                        $options = $record->options()->withCount('votes')->orderByDesc('votes_count')->get();
                        $totalVotes = $record->votes()->count();
                        $uniqueVoters = $record->unique_voters_count;

                        $html = '<div style="padding: 16px;">';
                        $html .= '<div style="display: flex; gap: 24px; margin-bottom: 24px;">';
                        $html .= '<div style="background: #f0fdf4; padding: 12px 20px; border-radius: 8px;">';
                        $html .= '<div style="font-size: 24px; font-weight: 700; color: #16a34a;">' . $totalVotes . '</div>';
                        $html .= '<div style="font-size: 12px; color: #6b7280;">Összes szavazat</div>';
                        $html .= '</div>';
                        $html .= '<div style="background: #eff6ff; padding: 12px 20px; border-radius: 8px;">';
                        $html .= '<div style="font-size: 24px; font-weight: 700; color: #2563eb;">' . $uniqueVoters . '</div>';
                        $html .= '<div style="font-size: 12px; color: #6b7280;">Szavazó</div>';
                        $html .= '</div>';
                        $html .= '</div>';

                        if ($options->isEmpty()) {
                            $html .= '<p style="color: #6b7280; text-align: center;">Nincsenek opciók ehhez a szavazáshoz.</p>';
                        } else {
                            foreach ($options as $option) {
                                $percentage = $totalVotes > 0 ? round(($option->votes_count / $totalVotes) * 100, 1) : 0;
                                $barWidth = $percentage;

                                $html .= '<div style="margin-bottom: 12px;">';
                                $html .= '<div style="display: flex; justify-content: space-between; margin-bottom: 4px;">';
                                $html .= '<span style="font-weight: 500;">' . e($option->label) . '</span>';
                                $html .= '<span style="color: #6b7280;">' . $option->votes_count . ' (' . $percentage . '%)</span>';
                                $html .= '</div>';
                                $html .= '<div style="height: 8px; background: #e5e7eb; border-radius: 9999px; overflow: hidden;">';
                                $html .= '<div style="height: 100%; width: ' . $barWidth . '%; background: #3b82f6; border-radius: 9999px;"></div>';
                                $html .= '</div>';
                                $html .= '</div>';
                            }
                        }

                        $html .= '</div>';

                        return new HtmlString($html);
                    }),

                EditAction::make()
                    ->form([
                        Forms\Components\TextInput::make('title')
                            ->label('Cím')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label('Leírás')
                            ->rows(3),

                        Forms\Components\Toggle::make('is_multiple_choice')
                            ->label('Több opció választható')
                            ->reactive(),

                        Forms\Components\TextInput::make('max_votes_per_guest')
                            ->label('Maximum szavazat/vendég')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10)
                            ->visible(fn (callable $get) => $get('is_multiple_choice')),

                        Forms\Components\Toggle::make('show_results_before_vote')
                            ->label('Eredmények láthatók szavazás előtt'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktív'),

                        Forms\Components\DateTimePicker::make('close_at')
                            ->label('Automatikus lezárás'),
                    ]),

                DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

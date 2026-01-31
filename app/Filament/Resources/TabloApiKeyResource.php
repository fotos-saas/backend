<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TabloApiKeyResource\Pages;
use App\Models\TabloApiKey;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class TabloApiKeyResource extends BaseResource
{

    protected static ?string $model = TabloApiKey::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'API Kulcsok';

    protected static ?string $modelLabel = 'API Kulcs';

    protected static ?string $pluralModelLabel = 'API Kulcsok';

    protected static string | UnitEnum | null $navigationGroup = 'Tabló';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('API Kulcs Adatok')
                    ->icon('heroicon-o-key')
                    ->components([
                        Forms\Components\TextInput::make('name')
                            ->label('Megnevezés')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Adj nevet az API kulcsnak az azonosításhoz (pl. "Helyi rendszer", "Teszt kulcs")'),

                        Forms\Components\TextInput::make('key')
                            ->label('API Kulcs')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Az API kulcs létrehozáskor automatikusan generálódik')
                            ->visible(fn ($record) => $record !== null),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktív')
                            ->default(true)
                            ->helperText('Inaktív kulccsal nem lehet hitelesíteni'),
                    ])
                    ->columns(1),

                Section::make('Használat')
                    ->icon('heroicon-o-information-circle')
                    ->components([
                        Forms\Components\Placeholder::make('last_used_at')
                            ->label('Utoljára használva')
                            ->content(fn ($record) => $record?->last_used_at?->format('Y-m-d H:i:s') ?? 'Még nem használták'),

                        Forms\Components\Placeholder::make('created_at')
                            ->label('Létrehozva')
                            ->content(fn ($record) => $record?->created_at?->format('Y-m-d H:i:s') ?? '-'),

                        Forms\Components\Placeholder::make('usage_hint')
                            ->label('Használat')
                            ->content('Az API-t a `X-Tablo-Api-Key` HTTP fejléccel kell hívni.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->hidden(fn ($record) => $record === null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Megnevezés')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('key')
                    ->label('API Kulcs')
                    ->formatStateUsing(fn (string $state): string => substr($state, 0, 12).'...')
                    ->copyable()
                    ->copyMessage('API kulcs másolva!')
                    ->copyMessageDuration(1500)
                    ->icon('heroicon-o-clipboard-document'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktív')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Utoljára használva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('Még nem használták')
                    ->description(fn ($record) => $record->last_used_at?->diffForHumans()),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Státusz')
                    ->trueLabel('Aktív')
                    ->falseLabel('Inaktív'),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('copy_key')
                        ->label('Kulcs másolása')
                        ->icon('heroicon-o-clipboard-document')
                        ->color('info')
                        ->action(function (TabloApiKey $record) {
                            Notification::make()
                                ->title('API kulcs: '.$record->key)
                                ->body('Másold ki a kulcsot!')
                                ->info()
                                ->persistent()
                                ->send();
                        }),
                    Action::make('toggle_active')
                        ->label(fn (TabloApiKey $record) => $record->is_active ? 'Deaktiválás' : 'Aktiválás')
                        ->icon(fn (TabloApiKey $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->color(fn (TabloApiKey $record) => $record->is_active ? 'warning' : 'success')
                        ->requiresConfirmation()
                        ->action(function (TabloApiKey $record) {
                            $record->update(['is_active' => ! $record->is_active]);
                            Notification::make()
                                ->title($record->is_active ? 'API kulcs aktiválva' : 'API kulcs deaktiválva')
                                ->success()
                                ->send();
                        }),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->label('Műveletek')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTabloApiKeys::route('/'),
            'create' => Pages\CreateTabloApiKey::route('/create'),
            'edit' => Pages\EditTabloApiKey::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TabloPartnerResource\Pages;
use App\Models\TabloPartner;
use BackedEnum;
use UnitEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class TabloPartnerResource extends BaseResource
{

    protected static ?string $model = TabloPartner::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Partnerek';

    protected static ?string $modelLabel = 'Partner';

    protected static ?string $pluralModelLabel = 'Partnerek';

    protected static string | UnitEnum | null $navigationGroup = 'Tabló';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Partner Adatok')
                    ->icon('heroicon-o-building-office-2')
                    ->components([
                        Forms\Components\TextInput::make('name')
                            ->label('Név')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, $record) {
                                if (! $record) {
                                    $set('slug', \Illuminate\Support\Str::slug($state));
                                }
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('URL-barát azonosító (automatikusan generálódik)'),

                        Forms\Components\TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-o-envelope'),

                        Forms\Components\TextInput::make('phone')
                            ->label('Telefonszám')
                            ->tel()
                            ->maxLength(50)
                            ->prefixIcon('heroicon-o-phone'),

                        Forms\Components\TextInput::make('local_id')
                            ->label('Helyi ID')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Azonosító a helyi rendszerben'),
                    ])
                    ->columns(2),

                Section::make('Statisztikák')
                    ->icon('heroicon-o-chart-bar')
                    ->components([
                        Forms\Components\Placeholder::make('projects_count')
                            ->label('Projektek száma')
                            ->content(fn ($record) => $record ? $record->projects()->count() : 0),

                        Forms\Components\Placeholder::make('created_at')
                            ->label('Létrehozva')
                            ->content(fn ($record) => $record?->created_at?->format('Y-m-d H:i') ?? '-'),
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
                    ->label('Név')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('local_id')
                    ->label('Helyi ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('projects_count')
                    ->label('Projektek')
                    ->counts('projects')
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->actions([
                ActionGroup::make([
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
            'index' => Pages\ListTabloPartners::route('/'),
            'create' => Pages\CreateTabloPartner::route('/create'),
            'edit' => Pages\EditTabloPartner::route('/{record}/edit'),
        ];
    }
}

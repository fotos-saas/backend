<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TabloStatusResource\Pages;
use App\Models\TabloStatus;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class TabloStatusResource extends BaseResource
{

    protected static ?string $model = TabloStatus::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Tabló Státuszok';

    protected static ?string $modelLabel = 'Tabló Státusz';

    protected static ?string $pluralModelLabel = 'Tabló Státuszok';

    protected static string|UnitEnum|null $navigationGroup = 'Tabló';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Státusz adatok')
                    ->description('A felhasználók által látható státusz')
                    ->components([
                        Forms\Components\TextInput::make('name')
                            ->label('Név')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('pl. Folyamatban')
                            ->helperText('A felhasználóknak megjelenő név'),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Automatikusan generálódik a névből'),

                        Forms\Components\Select::make('color')
                            ->label('Szín')
                            ->required()
                            ->options(TabloStatus::getColorOptions())
                            ->default('gray')
                            ->helperText('A státusz megjelenítési színe'),

                        Forms\Components\TextInput::make('icon')
                            ->label('Ikon')
                            ->placeholder('heroicon-o-clock')
                            ->helperText('Heroicon név (pl. heroicon-o-clock, heroicon-o-check-circle)'),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sorrend')
                            ->numeric()
                            ->default(0)
                            ->helperText('Alacsonyabb szám = előrébb'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktív')
                            ->default(true)
                            ->helperText('Inaktív státusz nem választható'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width('50px'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Név')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('color')
                    ->label('Szín')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => TabloStatus::getColorOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'gray' => 'gray',
                        'blue' => 'info',
                        'amber' => 'warning',
                        'green' => 'success',
                        'purple' => 'primary',
                        'red' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('icon')
                    ->label('Ikon')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktív')
                    ->boolean(),

                Tables\Columns\TextColumn::make('tablo_projects_count')
                    ->label('Projektek')
                    ->counts('tabloProjects')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTabloStatuses::route('/'),
            'create' => Pages\CreateTabloStatus::route('/create'),
            'edit' => Pages\EditTabloStatus::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\PrintSizes;

use App\Filament\Resources\BaseResource;

use App\Filament\Resources\PrintSizes\Pages\ListPrintSizes;
use App\Models\PrintSize;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PrintSizeResource extends BaseResource
{

    protected static function getPermissionKey(): string
    {
        return 'print-sizes';
    }
    protected static ?string $model = PrintSize::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Nyomtatási méretek';

    protected static ?string $modelLabel = 'Nyomtatási méret';

    protected static ?string $pluralModelLabel = 'Nyomtatási méretek';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'Csomagbeállítások';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Megnevezés')
                    ->placeholder('pl. 10x15 cm papír')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('width_mm')
                    ->label('Szélesség (mm)')
                    ->numeric()
                    ->minValue(10)
                    ->maxValue(2000),

                TextInput::make('height_mm')
                    ->label('Magasság (mm)')
                    ->numeric()
                    ->minValue(10)
                    ->maxValue(2000),

                TextInput::make('weight_grams')
                    ->label('Súly (gramm)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(10000)
                    ->suffix('g')
                    ->helperText('A nyomtatott kép becsült súlya grammban'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Megnevezés')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('dimensions')
                    ->label('Méret')
                    ->state(function (PrintSize $record) {
                        if ($record->width_mm && $record->height_mm) {
                            return "{$record->width_mm} × {$record->height_mm} mm";
                        }

                        return '—';
                    }),

                TextColumn::make('weight_grams')
                    ->label('Súly')
                    ->numeric()
                    ->suffix(' g')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrintSizes::route('/'),
        ];
    }
}

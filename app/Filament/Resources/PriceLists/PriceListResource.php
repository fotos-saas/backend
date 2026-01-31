<?php

namespace App\Filament\Resources\PriceLists;

use App\Filament\Resources\BaseResource;

use App\Filament\Resources\PriceLists\Pages\CreatePriceList;
use App\Filament\Resources\PriceLists\Pages\EditPriceList;
use App\Filament\Resources\PriceLists\Pages\ListPriceLists;
use App\Filament\Resources\PriceLists\RelationManagers\PricesRelationManager;
use App\Models\PriceList;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PriceListResource extends BaseResource
{

    protected static function getPermissionKey(): string
    {
        return 'price-lists';
    }
    protected static ?string $model = PriceList::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $navigationLabel = 'Árlisták';

    protected static ?string $modelLabel = 'Árlista';

    protected static ?string $pluralModelLabel = 'Árlisták';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Csomagbeállítások';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Árlista neve')
                    ->required()
                    ->maxLength(255),

                Toggle::make('is_default')
                    ->label('Alapértelmezett árlista')
                    ->helperText('Csak egy árlista lehet alapértelmezett. Ez érvényesül, ha nincs más beállítás.')
                    ->afterStateUpdated(function ($state, $record) {
                        if ($state && $record) {
                            $record->markAsDefault();
                        }
                    }),

                Select::make('default_print_size_id')
                    ->label('Alapértelmezett fotóméret')
                    ->relationship('defaultPrintSize', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Ez a méret kerül automatikusan a kosárba (1 db), amikor a felhasználó kiválaszt egy képet. Ha nincs beállítva, a legolcsóbb méret lesz az alapértelmezett.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Név')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_default')
                    ->label('Alapértelmezett')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable(),

                TextColumn::make('defaultPrintSize.name')
                    ->label('Alapértelmezett méret')
                    ->default('—')
                    ->badge()
                    ->color('success'),

                TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                DeleteAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            PricesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceLists::route('/'),
            'create' => CreatePriceList::route('/create'),
            'edit' => EditPriceList::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\ShippingMethods;

use App\Filament\Resources\BaseResource;

use App\Filament\Resources\ShippingMethods\Pages\EditShippingMethod;
use App\Filament\Resources\ShippingMethods\Pages\ListShippingMethods;
use App\Filament\Resources\ShippingMethods\Schemas\ShippingMethodForm;
use App\Filament\Resources\ShippingMethods\Tables\ShippingMethodsTable;
use App\Models\ShippingMethod;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ShippingMethodResource extends BaseResource
{

    protected static function getPermissionKey(): string
    {
        return 'shipping-methods';
    }
    protected static ?string $model = ShippingMethod::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Szállítási módok';

    protected static ?string $modelLabel = 'Szállítási mód';

    protected static ?string $pluralModelLabel = 'Szállítási módok';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'Szállítás és Fizetés';
    }

    public static function form(Schema $schema): Schema
    {
        return ShippingMethodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShippingMethodsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingMethods::route('/'),
            'edit' => EditShippingMethod::route('/{record}/edit'),
        ];
    }
}

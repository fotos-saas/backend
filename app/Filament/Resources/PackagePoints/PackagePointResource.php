<?php

namespace App\Filament\Resources\PackagePoints;

use App\Filament\Resources\BaseResource;

use App\Filament\Resources\PackagePoints\Pages\EditPackagePoint;
use App\Filament\Resources\PackagePoints\Pages\ListPackagePoints;
use App\Filament\Resources\PackagePoints\Schemas\PackagePointForm;
use App\Filament\Resources\PackagePoints\Tables\PackagePointsTable;
use App\Models\PackagePoint;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PackagePointResource extends BaseResource
{

    protected static function getPermissionKey(): string
    {
        return 'package-points';
    }
    protected static ?string $model = PackagePoint::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $navigationLabel = 'Csomagpontok';

    protected static ?string $modelLabel = 'Csomagpont';

    protected static ?string $pluralModelLabel = 'Csomagpontok';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Szállítás és Fizetés';
    }

    public static function form(Schema $schema): Schema
    {
        return PackagePointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PackagePointsTable::configure($table);
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
            'index' => ListPackagePoints::route('/'),
            'edit' => EditPackagePoint::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\TabloPersonResource;

use App\Filament\Resources\TabloPersonResource\Pages\CreateTabloPerson;
use App\Filament\Resources\TabloPersonResource\Pages\EditTabloPerson;
use App\Filament\Resources\TabloPersonResource\Pages\ListTabloPersons;
use App\Filament\Resources\TabloPersonResource\Schemas\TabloPersonForm;
use App\Filament\Resources\TabloPersonResource\Tables\TabloPersonsTable;
use App\Models\TabloPerson;
use BackedEnum;
use UnitEnum;
use App\Filament\Resources\BaseResource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TabloPersonResource extends BaseResource
{
    protected static ?string $model = TabloPerson::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Személyek';

    protected static ?string $modelLabel = 'Személy';

    protected static ?string $pluralModelLabel = 'Személyek';

    protected static string|UnitEnum|null $navigationGroup = 'Tabló';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return TabloPersonForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TabloPersonsTable::configure($table);
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
            'index' => ListTabloPersons::route('/'),
            'create' => CreateTabloPerson::route('/create'),
            'edit' => EditTabloPerson::route('/{record}/edit'),
        ];
    }
}

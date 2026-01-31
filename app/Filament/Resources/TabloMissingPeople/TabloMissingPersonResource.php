<?php

namespace App\Filament\Resources\TabloMissingPeople;

use App\Filament\Resources\TabloMissingPeople\Pages\CreateTabloMissingPerson;
use App\Filament\Resources\TabloMissingPeople\Pages\EditTabloMissingPerson;
use App\Filament\Resources\TabloMissingPeople\Pages\ListTabloMissingPeople;
use App\Filament\Resources\TabloMissingPeople\Schemas\TabloMissingPersonForm;
use App\Filament\Resources\TabloMissingPeople\Tables\TabloMissingPeopleTable;
use App\Models\TabloMissingPerson;
use BackedEnum;
use UnitEnum;
use App\Filament\Resources\BaseResource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TabloMissingPersonResource extends BaseResource
{
    protected static ?string $model = TabloMissingPerson::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Hiányzó személyek';

    protected static ?string $modelLabel = 'Hiányzó személy';

    protected static ?string $pluralModelLabel = 'Hiányzó személyek';

    protected static string|UnitEnum|null $navigationGroup = 'Tabló';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return TabloMissingPersonForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TabloMissingPeopleTable::configure($table);
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
            'index' => ListTabloMissingPeople::route('/'),
            'create' => CreateTabloMissingPerson::route('/create'),
            'edit' => EditTabloMissingPerson::route('/{record}/edit'),
        ];
    }
}

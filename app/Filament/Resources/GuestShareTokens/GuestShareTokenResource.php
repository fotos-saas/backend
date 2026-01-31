<?php

namespace App\Filament\Resources\GuestShareTokens;

use App\Filament\Resources\BaseResource;

use App\Filament\Resources\GuestShareTokens\Pages\CreateGuestShareToken;
use App\Filament\Resources\GuestShareTokens\Pages\EditGuestShareToken;
use App\Filament\Resources\GuestShareTokens\Pages\ListGuestShareTokens;
use App\Models\GuestShareToken;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GuestShareTokenResource extends BaseResource
{

    protected static function getPermissionKey(): string
    {
        return 'guest-share-tokens';
    }
    protected static ?string $model = GuestShareToken::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedShare;

    protected static ?string $navigationLabel = 'Vendég Megosztások';

    protected static ?string $modelLabel = 'Vendég Megosztás';

    protected static ?string $pluralModelLabel = 'Vendég Megosztások';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
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
            'index' => ListGuestShareTokens::route('/'),
            'create' => CreateGuestShareToken::route('/create'),
            'edit' => EditGuestShareToken::route('/{record}/edit'),
        ];
    }
}

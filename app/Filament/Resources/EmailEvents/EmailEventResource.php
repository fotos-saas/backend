<?php

namespace App\Filament\Resources\EmailEvents;

use App\Filament\Resources\BaseResource;

use App\Filament\Resources\EmailEvents\Pages\CreateEmailEvent;
use App\Filament\Resources\EmailEvents\Pages\EditEmailEvent;
use App\Filament\Resources\EmailEvents\Pages\ListEmailEvents;
use App\Filament\Resources\EmailEvents\Schemas\EmailEventForm;
use App\Filament\Resources\EmailEvents\Tables\EmailEventsTable;
use App\Models\EmailEvent;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EmailEventResource extends BaseResource
{

    protected static function getPermissionKey(): string
    {
        return 'email-events';
    }
    protected static ?string $model = EmailEvent::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $navigationLabel = 'Email Események';

    protected static ?string $modelLabel = 'Email Esemény';

    protected static ?string $pluralModelLabel = 'Email Események';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Email Rendszer';
    }

    public static function form(Schema $schema): Schema
    {
        return EmailEventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmailEventsTable::configure($table);
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
            'index' => ListEmailEvents::route('/'),
            'create' => CreateEmailEvent::route('/create'),
            'edit' => EditEmailEvent::route('/{record}/edit'),
        ];
    }
}

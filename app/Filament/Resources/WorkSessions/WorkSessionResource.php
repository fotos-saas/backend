<?php

namespace App\Filament\Resources\WorkSessions;

use App\Filament\Resources\BaseResource;

use App\Filament\Resources\WorkSessions\Pages\CreateWorkSession;
use App\Filament\Resources\WorkSessions\Pages\EditWorkSession;
use App\Filament\Resources\WorkSessions\Pages\ListWorkSessions;
use App\Filament\Resources\WorkSessions\Schemas\WorkSessionForm;
use App\Filament\Resources\WorkSessions\Tables\WorkSessionsTable;
use App\Models\WorkSession;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WorkSessionResource extends BaseResource
{

    protected static ?string $model = WorkSession::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Munkamenetek';

    protected static ?string $modelLabel = 'Munkamenet';

    protected static ?string $pluralModelLabel = 'Munkamenetek';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return WorkSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkSessionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        $relations = [];

        // Almunkamenetek tab - mindig megjelenik (saját canViewForRecord ellenőrzéssel)
        $relations[] = RelationManagers\ChildSessionsRelationManager::class;

        // Only show relation managers if user has permission
        if (static::canAccessRelation('albums')) {
            $relations[] = RelationManagers\AlbumsRelationManager::class;
        }

        if (static::canAccessRelation('users')) {
            $relations[] = RelationManagers\UsersRelationManager::class;
        }

        return $relations;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkSessions::route('/'),
            'create' => CreateWorkSession::route('/create'),
            'edit' => EditWorkSession::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

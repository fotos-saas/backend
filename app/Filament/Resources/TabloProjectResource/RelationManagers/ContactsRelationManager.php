<?php

namespace App\Filament\Resources\TabloProjectResource\RelationManagers;

use App\Filament\Resources\TabloProjectResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    /**
     * Determine if the relation manager can be viewed for the given record.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return TabloProjectResource::canAccessRelation('contacts');
    }

    /**
     * Badge showing count of contacts.
     */
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->contacts()->count();

        return $count > 0 ? (string) $count : null;
    }

    protected static ?string $title = 'Kapcsolattartók';

    protected static ?string $modelLabel = 'Kapcsolattartó';

    protected static ?string $pluralModelLabel = 'Kapcsolattartók';

    protected static \BackedEnum|string|null $icon = 'heroicon-o-users';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Forms\Components\TextInput::make('name')
                    ->label('Név')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->maxLength(255)
                    ->prefixIcon('heroicon-o-envelope'),

                Forms\Components\TextInput::make('phone')
                    ->label('Telefonszám')
                    ->tel()
                    ->maxLength(50)
                    ->prefixIcon('heroicon-o-phone'),

                Forms\Components\Textarea::make('note')
                    ->label('Megjegyzés')
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_primary')
                    ->label('Elsődleges kapcsolattartó')
                    ->helperText('Ez a kapcsolattartó jelenik meg a frontend-tablo kezdőlapon')
                    ->default(false)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Elsődleges')
                    ->boolean()
                    ->sortable()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Név')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-o-envelope'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-phone'),

                Tables\Columns\TextColumn::make('note')
                    ->label('Megjegyzés')
                    ->limit(50)
                    ->toggleable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Hozzáadva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->headerActions([
                CreateAction::make()
                    ->label('Új Kapcsolattartó')
                    ->modalWidth('lg'),
            ])
            ->actions([
                EditAction::make()
                    ->modalWidth('lg'),
                DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

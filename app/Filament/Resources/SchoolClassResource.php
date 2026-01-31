<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SchoolClassResource\Pages;
use App\Filament\Resources\SchoolClassResource\RelationManagers\UsersRelationManager;
use App\Models\SchoolClass;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class SchoolClassResource extends BaseResource
{
    protected static ?string $model = SchoolClass::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Osztályok';

    protected static ?string $modelLabel = 'Osztály';

    protected static ?string $pluralModelLabel = 'Osztályok';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'Iskolai Adatok';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Alapadatok')
                    ->components([
                        Forms\Components\TextInput::make('school')
                            ->label('Iskola neve')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('pl. Petőfi Sándor Általános Iskola'),

                        Forms\Components\TextInput::make('grade')
                            ->label('Évfolyam')
                            ->required()
                            ->maxLength(20)
                            ->placeholder('pl. 12. vagy Ballagás'),

                        Forms\Components\TextInput::make('label')
                            ->label('Osztály jele')
                            ->required()
                            ->maxLength(10)
                            ->placeholder('pl. 9.A')
                            ->helperText('Az osztály rövidített jele (pl. 9.A, 12.B)'),
                    ])
                    ->columns(2),

                Section::make('Statisztikák')
                    ->components([
                        Forms\Components\Placeholder::make('users_count')
                            ->label('Tanulók száma')
                            ->content(fn ($record) => $record ? $record->users()->count() : 0),

                        Forms\Components\Placeholder::make('albums_count')
                            ->label('Albumok száma')
                            ->content(fn ($record) => $record ? $record->albums()->count() : 0),

                        Forms\Components\Placeholder::make('created_at')
                            ->label('Létrehozva')
                            ->content(fn ($record) => $record ? $record->created_at->format('Y-m-d H:i') : '-'),
                    ])
                    ->columns(3)
                    ->hidden(fn ($record) => $record === null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Osztály')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('school')
                    ->label('Iskola')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('grade')
                    ->label('Évfolyam')
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Tanulók')
                    ->counts('users')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('albums_count')
                    ->label('Albumok')
                    ->counts('albums')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->groups([
                Group::make('school')
                    ->label('Iskola')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false),
            ])
            ->defaultGroup('school')
            ->recordClasses(function ($record) {
                // Új rekord kiemelés 10 másodpercig
                $createdAt = $record->created_at;
                $tenSecondsAgo = now()->subSeconds(10);

                if ($createdAt && $createdAt->isAfter($tenSecondsAgo)) {
                    return 'fi-ta-row-new';
                }

                return null;
            })
            ->filters([
                Tables\Filters\TernaryFilter::make('grade')
                    ->label('Évfolyam')
                    ->nullable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, SchoolClass $record) {
                        // Ellenőrizzük, hogy vannak-e hozzárendelt tanulók vagy albumok
                        $usersCount = $record->users()->count();
                        $albumsCount = $record->albums()->count();

                        if ($usersCount > 0 || $albumsCount > 0) {
                            Notification::make()
                                ->title('Törlés nem lehetséges')
                                ->body("Az osztályhoz {$usersCount} tanuló és {$albumsCount} album tartozik. Törlés előtt távolítsd el ezeket!")
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action, $records) {
                            // Ellenőrizzük minden kiválasztott rekordot
                            $hasRelatedData = false;
                            foreach ($records as $record) {
                                if ($record->users()->count() > 0 || $record->albums()->count() > 0) {
                                    $hasRelatedData = true;
                                    break;
                                }
                            }

                            if ($hasRelatedData) {
                                Notification::make()
                                    ->title('Törlés nem lehetséges')
                                    ->body('A kiválasztott osztályok közül legalább egynek vannak hozzárendelt tanulói vagy albumai. Törlés előtt távolítsd el ezeket!')
                                    ->danger()
                                    ->send();

                                $action->cancel();
                            }
                        }),
                ]),
            ])
            ->modifyQueryUsing(function ($query) {
                $newSchoolClassId = session('new_school_class_id');

                if ($newSchoolClassId) {
                    return $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$newSchoolClassId])
                        ->orderBy('created_at', 'desc');
                }

                return $query->orderBy('created_at', 'desc');
            })
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchoolClasses::route('/'),
            'create' => Pages\CreateSchoolClass::route('/create'),
            'edit' => Pages\EditSchoolClass::route('/{record}/edit'),
        ];
    }
}

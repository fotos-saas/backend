<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailVariableResource\Pages;
use App\Models\EmailVariable;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class EmailVariableResource extends BaseResource
{

    protected static function getPermissionKey(): string
    {
        return 'email-variables';
    }
    protected static ?string $model = EmailVariable::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedVariable;

    protected static ?string $navigationLabel = 'Email Változók';

    protected static ?string $modelLabel = 'Email Változó';

    protected static ?string $pluralModelLabel = 'Email Változók';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Email Rendszer';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Változó adatok')
                    ->description('Egyedi email változók definiálása rekurzív hivatkozásokkal')
                    ->components([
                        Grid::make(2)
                            ->components([
                                Forms\Components\TextInput::make('key')
                                    ->label('Kulcs')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->regex('/^[a-z0-9_]+$/')
                                    ->placeholder('partner_name')
                                    ->helperText('Csak kisbetűk, számok és alulvonás (_)')
                                    ->columnSpan(1),

                                Forms\Components\Select::make('group')
                                    ->label('Csoport')
                                    ->options(config('email-system.variable_groups', [
                                        'user' => 'Felhasználó változók',
                                        'order' => 'Megrendelés változók',
                                        'album' => 'Album változók',
                                        'general' => 'Általános változók',
                                        'custom' => 'Egyedi változók',
                                    ]))
                                    ->default('general')
                                    ->required()
                                    ->columnSpan(1),
                            ]),

                        Forms\Components\Textarea::make('value')
                            ->label('Érték')
                            ->required()
                            ->rows(3)
                            ->maxLength(65535)
                            ->placeholder('Tabló Király - {current_year}')
                            ->helperText('Használhatsz más változókat: {kulcs}. Rekurzív feloldás támogatott.'),

                        Forms\Components\Textarea::make('description')
                            ->label('Leírás')
                            ->rows(2)
                            ->maxLength(65535)
                            ->placeholder('Mikor és hol használatos ez a változó?')
                            ->helperText('Admin felületen megjelenő leírás (opcionális)'),

                        Grid::make(2)
                            ->components([
                                Forms\Components\TextInput::make('priority')
                                    ->label('Prioritás')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(999)
                                    ->default(100)
                                    ->helperText('Alacsonyabb = hamarabb feldolgozódik')
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Aktív')
                                    ->helperText('Csak aktív változók kerülnek felhasználásra')
                                    ->default(true)
                                    ->columnSpan(1),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label('Kulcs')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Kulcs másolva!')
                    ->copyMessageDuration(1500),

                Tables\Columns\TextColumn::make('value')
                    ->label('Érték')
                    ->limit(50)
                    ->tooltip(fn (EmailVariable $record): string => $record->value)
                    ->searchable(),

                Tables\Columns\TextColumn::make('group')
                    ->label('Csoport')
                    ->badge()
                    ->colors([
                        'primary' => 'user',
                        'success' => 'order',
                        'warning' => 'album',
                        'info' => 'general',
                        'gray' => 'custom',
                    ])
                    ->formatStateUsing(fn (string $state): string => config("email-system.variable_groups.{$state}", $state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioritás')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktív')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Módosítva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->label('Csoport')
                    ->options(config('email-system.variable_groups', [
                        'user' => 'Felhasználó',
                        'order' => 'Megrendelés',
                        'album' => 'Album',
                        'general' => 'Általános',
                        'custom' => 'Egyedi',
                    ])),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktív')
                    ->trueLabel('Csak aktív')
                    ->falseLabel('Csak inaktív'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority', 'asc')
            ->modifyQueryUsing(function ($query) {
                $newRecordId = session('new_email_variable_id');
                if ($newRecordId) {
                    return $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$newRecordId])
                        ->orderBy('priority', 'asc');
                }

                return $query->orderBy('priority', 'asc');
            })
            ->recordClasses(function ($record) {
                $createdAt = $record->created_at;
                $tenSecondsAgo = now()->subSeconds(10);

                if ($createdAt && $createdAt->isAfter($tenSecondsAgo)) {
                    return 'fi-ta-row-new';
                }

                return null;
            });
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
            'index' => Pages\ListEmailVariables::route('/'),
            'create' => Pages\CreateEmailVariable::route('/create'),
            'edit' => Pages\EditEmailVariable::route('/{record}/edit'),
        ];
    }
}

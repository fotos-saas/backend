<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TabloSampleTemplateCategoryResource\Pages;
use App\Models\TabloSampleTemplateCategory;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class TabloSampleTemplateCategoryResource extends BaseResource
{
    protected static ?string $model = TabloSampleTemplateCategory::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static ?string $navigationLabel = 'Minta Kategóriák';

    protected static ?string $modelLabel = 'Minta Kategória';

    protected static ?string $pluralModelLabel = 'Minta Kategóriák';

    protected static string|UnitEnum|null $navigationGroup = 'Tabló';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kategória adatok')
                    ->description('A minta táblók kategorizálásához')
                    ->components([
                        Forms\Components\TextInput::make('name')
                            ->label('Név')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('pl. Mesés')
                            ->helperText('A kategória neve')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', \Illuminate\Support\Str::slug($state))),

                        // Slug rejtett, automatikusan generálódik
                        Forms\Components\Hidden::make('slug'),

                        Forms\Components\Textarea::make('description')
                            ->label('Leírás')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Opcionális leírás')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('icon')
                            ->label('Ikon')
                            ->placeholder('heroicon-o-sparkles')
                            ->helperText('Heroicon név (opcionális)'),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sorrend')
                            ->numeric()
                            ->default(0)
                            ->helperText('Alacsonyabb szám = előrébb'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktív')
                            ->default(true)
                            ->helperText('Inaktív kategória nem jelenik meg'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width('50px'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Név')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktív')
                    ->boolean(),

                Tables\Columns\TextColumn::make('templates_count')
                    ->label('Minták')
                    ->counts('templates')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\TabloSampleTemplateCategoryResource\RelationManagers\TemplatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTabloSampleTemplateCategories::route('/'),
            'create' => Pages\CreateTabloSampleTemplateCategory::route('/create'),
            'edit' => Pages\EditTabloSampleTemplateCategory::route('/{record}/edit'),
        ];
    }
}

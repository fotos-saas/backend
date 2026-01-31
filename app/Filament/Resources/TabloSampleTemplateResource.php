<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TabloSampleTemplateResource\Pages;
use App\Models\TabloSampleTemplate;
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

class TabloSampleTemplateResource extends BaseResource
{
    protected static ?string $model = TabloSampleTemplate::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Minta Táblók';

    protected static ?string $modelLabel = 'Minta Tabló';

    protected static ?string $pluralModelLabel = 'Minta Táblók';

    protected static string|UnitEnum|null $navigationGroup = 'Tabló';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Minta adatok')
                    ->description('A felhasználók által választható tabló minták')
                    ->components([
                        Forms\Components\TextInput::make('name')
                            ->label('Név')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('pl. Elegáns kék')
                            ->helperText('A minta neve')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', \Illuminate\Support\Str::slug($state))),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Automatikusan generálódik a névből'),

                        Forms\Components\Textarea::make('description')
                            ->label('Leírás')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Opcionális leírás a mintáról'),

                        Forms\Components\Select::make('categories')
                            ->label('Kategóriák')
                            ->multiple()
                            ->relationship('categories', 'name')
                            ->options(
                                TabloSampleTemplateCategory::active()
                                    ->ordered()
                                    ->pluck('name', 'id')
                            )
                            ->preload()
                            ->searchable()
                            ->helperText('A minta kategóriái'),

                        Forms\Components\TagsInput::make('tags')
                            ->label('Címkék')
                            ->placeholder('pl. új, népszerű')
                            ->helperText('Extra címkék (Enter-rel válassz el)'),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sorrend')
                            ->numeric()
                            ->default(0)
                            ->helperText('Alacsonyabb = előrébb'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktív')
                            ->default(true)
                            ->helperText('Inaktív minta nem jelenik meg'),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Kiemelt')
                            ->default(false)
                            ->helperText('Kiemelt minta előrébb jelenik meg'),
                    ])
                    ->columns(2),

                Section::make('Kép')
                    ->description('A minta megjelenítési képe')
                    ->components([
                        Forms\Components\FileUpload::make('image_path')
                            ->label('Minta kép')
                            ->image()
                            ->required()
                            ->directory('tablo-sample-templates')
                            ->preserveFilenames()
                            ->imageEditor()
                            ->imagePreviewHeight('200')
                            ->columnSpanFull()
                            ->helperText('Javasolt méret: 1200x900px'),
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

                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Kép')
                    ->disk('public')
                    ->width(80)
                    ->height(60),

                Tables\Columns\TextColumn::make('name')
                    ->label('Név')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('categories.name')
                    ->label('Kategóriák')
                    ->badge()
                    ->separator(',')
                    ->limitList(3),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Kiemelt')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktív')
                    ->boolean(),

                Tables\Columns\TextColumn::make('projects_count')
                    ->label('Választások')
                    ->counts('projects')
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTabloSampleTemplates::route('/'),
            'create' => Pages\CreateTabloSampleTemplate::route('/create'),
            'edit' => Pages\EditTabloSampleTemplate::route('/{record}/edit'),
        ];
    }
}

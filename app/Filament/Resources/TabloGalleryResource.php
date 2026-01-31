<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TabloGalleryResource\Pages;
use App\Filament\Resources\TabloGalleryResource\RelationManagers;
use App\Models\TabloGallery;
use BackedEnum;
use UnitEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class TabloGalleryResource extends BaseResource
{
    protected static function getPermissionKey(): string
    {
        return 'tablo-galleries';
    }

    protected static ?string $model = TabloGallery::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Fotógalériák';

    protected static ?string $modelLabel = 'Fotógaléria';

    protected static ?string $pluralModelLabel = 'Fotógalériák';

    protected static string|UnitEnum|null $navigationGroup = 'Tabló';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Galéria adatok')
                    ->description('A fotógaléria alapvető információi')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Galéria neve')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('pl. 2025-ös fotózás')
                            ->helperText('A galéria megnevezése'),

                        Forms\Components\Select::make('status')
                            ->label('Státusz')
                            ->options([
                                'active' => 'Aktív',
                                'archived' => 'Archivált',
                            ])
                            ->default('active')
                            ->required()
                            ->helperText('Archivált galériák nem jelennek meg'),

                        Forms\Components\Textarea::make('description')
                            ->label('Leírás')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Rövid leírás a galériáról...')
                            ->helperText('Opcionális leírás')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('max_retouch_photos')
                            ->label('Max retusálható fotók')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->default(5)
                            ->helperText('Hány fotót lehet retusálni ebben a galériában (1-20)'),
                    ])
                    ->columns(2),

                Section::make('Fotók feltöltése')
                    ->description('Képek hozzáadása a galériához')
                    ->schema([
                        Forms\Components\FileUpload::make('photos')
                            ->label('Képek')
                            ->multiple()
                            ->image()
                            ->maxFiles(50)
                            ->maxSize(10240) // 10MB
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg', 'image/webp'])
                            ->preserveFilenames()
                            ->directory('temp-gallery-photos')
                            ->helperText('Max 50 kép, egyenként max 10 MB. JPEG, PNG, WEBP formátumok.')
                            ->columnSpanFull()
                            ->dehydrated(false), // Ne mentse közvetlenül, majd a Page kezeli
                    ])
                    ->visible(fn ($record) => $record !== null), // Csak szerkesztésnél jelenjen meg
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('preview')
                    ->label('')
                    ->state(function (TabloGallery $record) {
                        $media = $record->getMedia('photos')->first();
                        return $media?->getUrl('thumb');
                    })
                    ->width(60)
                    ->height(60)
                    ->circular(false)
                    ->extraImgAttributes([
                        'style' => 'border-radius: 6px; object-fit: cover;',
                    ]),

                Tables\Columns\TextColumn::make('name')
                    ->label('Galéria neve')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (TabloGallery $record) => $record->description
                        ? mb_substr($record->description, 0, 50) . (mb_strlen($record->description) > 50 ? '...' : '')
                        : null
                    ),

                Tables\Columns\TextColumn::make('photos_count')
                    ->label('Képek száma')
                    ->getStateUsing(fn (TabloGallery $record): int => $record->getMedia('photos')->count())
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('projects_count')
                    ->label('Projektek')
                    ->counts('projects')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Státusz')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'archived' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Aktív',
                        'archived' => 'Archivált',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Státusz')
                    ->options([
                        'active' => 'Aktív',
                        'archived' => 'Archivált',
                    ]),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->label('')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('gray'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PhotosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTabloGalleries::route('/'),
            'create' => Pages\CreateTabloGallery::route('/create'),
            'edit' => Pages\EditTabloGallery::route('/{record}/edit'),
        ];
    }
}

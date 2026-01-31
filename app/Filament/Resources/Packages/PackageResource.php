<?php

namespace App\Filament\Resources\Packages;

use App\Filament\Resources\BaseResource;

use App\Filament\Resources\Packages\Pages\CreatePackage;
use App\Filament\Resources\Packages\Pages\EditPackage;
use App\Filament\Resources\Packages\Pages\ListPackages;
use App\Filament\Resources\Packages\RelationManagers\ItemsRelationManager;
use App\Models\Coupon;
use App\Models\Package;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PackageResource extends BaseResource
{

    protected static function getPermissionKey(): string
    {
        return 'packages';
    }
    protected static ?string $model = Package::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $navigationLabel = 'Csomagok';

    protected static ?string $modelLabel = 'Csomag';

    protected static ?string $pluralModelLabel = 'Csomagok';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Csomagbeállítások';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Alapadatok')
                    ->schema([
                        TextInput::make('name')
                            ->label('Csomag neve')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('price')
                            ->label('Csomag ára')
                            ->suffix('Ft')
                            ->nullable()
                            ->helperText('A csomag teljes ára forintban')
                            ->mask(RawJs::make('$money($input, \' \', \'.\', 0)'))
                            ->dehydrateStateUsing(fn ($state) => $state ? (int) str_replace([' ', '.', ','], '', $state) : null)
                            ->rules(['nullable', 'numeric', 'min:0']),

                        TextInput::make('selectable_photos_count')
                            ->label('Kiválasztható képek száma')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required()
                            ->suffix('db')
                            ->helperText('Hány képet választhat ki a felhasználó ebben a csomagban a képei közül'),
                    ])
                    ->columns(2),

                Section::make('Kupon beállítások')
                    ->description('Határozd meg, mely kuponok használhatók ennél a csomagnál')
                    ->schema([
                        Select::make('coupon_policy')
                            ->label('Kupon házirend')
                            ->options([
                                'all' => 'Minden érvényes kupon használható',
                                'specific' => 'Csak meghatározott kuponok',
                                'none' => 'Egyik kupon sem használható',
                            ])
                            ->default('all')
                            ->required()
                            ->live(),

                        Select::make('allowed_coupon_ids')
                            ->label('Engedélyezett kuponok')
                            ->options(fn () => Coupon::all()->pluck('code', 'id')->toArray())
                            ->multiple()
                            ->searchable()
                            ->visible(fn ($get) => $get('coupon_policy') === 'specific')
                            ->helperText('Válaszd ki, mely kuponok használhatók ennél a csomagnál'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Csomag neve')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Ár')
                    ->formatStateUsing(fn ($state) => $state
                        ? number_format($state, 0, ',', ' ').' Ft'
                        : '—')
                    ->sortable(),

                TextColumn::make('selectable_photos_count')
                    ->label('Képek száma')
                    ->suffix(' db')
                    ->sortable(),

                TextColumn::make('album.title')
                    ->label('Album')
                    ->searchable()
                    ->sortable()
                    ->default('—'),

                TextColumn::make('coupon_policy')
                    ->label('Kupon szabály')
                    ->formatStateUsing(function ($state, Package $record) {
                        return match ($state) {
                            'all' => 'Mind',
                            'none' => 'Egyik sem',
                            'specific' => Coupon::whereIn('id', $record->allowed_coupon_ids ?? [])
                                ->pluck('code')
                                ->join(', ') ?: 'Nincs kiválasztva',
                            default => '—',
                        };
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'all' => 'success',
                        'none' => 'danger',
                        'specific' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPackages::route('/'),
            'create' => CreatePackage::route('/create'),
            'edit' => EditPackage::route('/{record}/edit'),
        ];
    }
}

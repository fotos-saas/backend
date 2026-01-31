<?php

namespace App\Filament\Resources\Coupons;

use App\Filament\Resources\BaseResource;

use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Models\Coupon;
use BackedEnum;
use Filament\Schemas\Components\DateTimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Select;
use Filament\Schemas\Components\TagsInput;
use Filament\Schemas\Components\Textarea;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CouponResource extends BaseResource
{

    protected static function getPermissionKey(): string
    {
        return 'coupons';
    }
    protected static ?string $model = Coupon::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $navigationLabel = 'Kuponok';

    protected static ?string $modelLabel = 'Kupon';

    protected static ?string $pluralModelLabel = 'Kuponok';

    protected static ?int $navigationSort = 4;

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
                        TextInput::make('code')
                            ->label('Kuponkód')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->columnSpan(1),

                        Select::make('type')
                            ->label('Típus')
                            ->options([
                                'percent' => 'Százalék',
                                'amount' => 'Fix összeg',
                            ])
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('value')
                            ->label('Érték')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->columnSpan(1),

                        Toggle::make('enabled')
                            ->label('Engedélyezve')
                            ->default(true)
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Korlátozások')
                    ->schema([
                        DateTimePicker::make('expires_at')
                            ->label('Lejárati dátum')
                            ->nullable()
                            ->native(false),

                        TextInput::make('min_order_value')
                            ->label('Min. rendelési érték (HUF)')
                            ->numeric()
                            ->minValue(0)
                            ->nullable()
                            ->suffix('Ft')
                            ->mask(RawJs::make('$money($input, \' \', \'.\', 0)')),

                        TextInput::make('max_usage')
                            ->label('Max. felhasználás')
                            ->numeric()
                            ->minValue(1)
                            ->nullable()
                            ->helperText('Hányszor használható fel összesen a kupon'),

                        TagsInput::make('allowed_emails')
                            ->label('Engedélyezett e-mailek')
                            ->nullable()
                            ->helperText('Hagyd üresen, ha mindenki használhatja'),

                        Textarea::make('description')
                            ->label('Leírás')
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kuponkód')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('type')
                    ->label('Típus')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'percent' => 'success',
                        'amount' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'percent' => 'Százalék',
                        'amount' => 'Fix összeg',
                        default => $state,
                    }),

                TextColumn::make('value')
                    ->label('Érték')
                    ->state(function (Coupon $record): string {
                        if ($record->type === 'percent') {
                            return "{$record->value}%";
                        }

                        return number_format($record->value, 0, ',', ' ').' Ft';
                    }),

                IconColumn::make('enabled')
                    ->label('Aktív')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Lejár')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->default('—'),

                TextColumn::make('usage')
                    ->label('Felhasználás')
                    ->state(function (Coupon $record): string {
                        if ($record->max_usage) {
                            return "{$record->usage_count} / {$record->max_usage}";
                        }

                        return (string) $record->usage_count;
                    }),

                TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => ListCoupons::route('/'),
            'create' => CreateCoupon::route('/create'),
            'edit' => EditCoupon::route('/{record}/edit'),
        ];
    }
}

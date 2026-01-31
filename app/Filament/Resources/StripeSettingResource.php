<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StripeSettingResource\Pages;
use App\Models\StripeSetting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StripeSettingResource extends BaseResource
{

    protected static function getPermissionKey(): string
    {
        return 'stripe-settings';
    }
    protected static ?string $model = StripeSetting::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Stripe Beállítások';

    protected static ?string $modelLabel = 'Stripe Beállítás';

    protected static ?string $pluralModelLabel = 'Stripe Beállítások';

    protected static ?int $navigationSort = 12;

    public static function getNavigationGroup(): ?string
    {
        return 'Beállítások';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('API Kulcsok')
                    ->description('Stripe API kulcsok beállítása')
                    ->columns(2)
                    ->components([
                        TextInput::make('public_key')
                            ->label('Publishable Key')
                            ->placeholder('pk_test_...')
                            ->helperText('Stripe Publishable Key (nyilvános kulcs)')
                            ->nullable()
                            ->columnSpanFull(),

                        TextInput::make('secret_key')
                            ->label('Secret Key')
                            ->placeholder('sk_test_...')
                            ->password()
                            ->revealable()
                            ->helperText('Stripe Secret Key (titkos kulcs)')
                            ->nullable()
                            ->columnSpanFull(),

                        TextInput::make('webhook_secret')
                            ->label('Webhook Secret')
                            ->placeholder('whsec_...')
                            ->password()
                            ->revealable()
                            ->helperText('Stripe Webhook Secret (webhook titkos kulcs)')
                            ->nullable()
                            ->columnSpanFull(),
                    ]),

                Section::make('Beállítások')
                    ->columns(2)
                    ->components([
                        Toggle::make('is_test_mode')
                            ->label('Teszt mód')
                            ->helperText('Ha be van kapcsolva, teszt módban működik a Stripe')
                            ->default(true),

                        Toggle::make('is_active')
                            ->label('Aktív')
                            ->helperText('Ha be van kapcsolva, a Stripe fizetés elérhető lesz')
                            ->default(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_key')
                    ->label('Publishable Key')
                    ->limit(30)
                    ->copyable(),

                IconColumn::make('is_test_mode')
                    ->label('Teszt mód')
                    ->boolean()
                    ->trueIcon('heroicon-o-beaker')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success'),

                IconColumn::make('is_active')
                    ->label('Aktív')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                TextColumn::make('updated_at')
                    ->label('Frissítve')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStripeSettings::route('/'),
            'create' => Pages\CreateStripeSetting::route('/create'),
            'edit' => Pages\EditStripeSetting::route('/{record}/edit'),
        ];
    }
}

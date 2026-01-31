<?php

namespace App\Filament\Resources\WorkSessions\Schemas;

use App\Enums\TabloModeType;
use App\Filament\Resources\WorkSessions\WorkSessionResource;
use App\Filament\Schemas\AccessCodeSection;
use App\Models\Coupon;
use App\Models\Package;
use App\Models\Price;
use App\Models\WorkSession;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class WorkSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Munkamenet')
                    ->tabs([
                        Tabs\Tab::make('Alapadatok')
                            ->icon('heroicon-o-information-circle')
                            ->visible(fn () => WorkSessionResource::canAccessTab('basic'))
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Név')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('pl: 9.A Osztály - Tavaszi Fotózás'),

                                Forms\Components\Textarea::make('description')
                                    ->label('Leírás')
                                    ->rows(3)
                                    ->maxLength(65535)
                                    ->placeholder('Részletes leírás a munkamenetről...')
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('status')
                                    ->label('Státusz')
                                    ->options([
                                        'active' => 'Aktív',
                                        'inactive' => 'Inaktív',
                                        'archived' => 'Archivált',
                                    ])
                                    ->default('active')
                                    ->required(),
                            ])
                            ->columns(2),

                        Tabs\Tab::make('Belépési módok')
                            ->icon('heroicon-o-key')
                            ->visible(fn () => WorkSessionResource::canAccessTab('access-methods'))
                            ->schema([
                                // Újrafelhasználható 6 számjegyű kód section (közös AccessCodeSection)
                                AccessCodeSection::make(
                                    modelClass: WorkSession::class,
                                    tableName: 'work_sessions',
                                    defaultDays: 30,
                                    useLegacyFields: true  // digit_code_* mezők használata
                                ),

                                Section::make('Megosztási link')
                                    ->description('Share link hozzáférés beállítása')
                                    ->schema([
                                        Forms\Components\Toggle::make('share_enabled')
                                            ->label('Megosztási link engedélyezése')
                                            ->helperText('Automatikusan generálódik bekapcsoláskor')
                                            ->live()
                                            ->afterStateUpdated(function ($state, $set, $get) {
                                                if ($state) {
                                                    // Ha bekapcsolják, generáljunk tokent (ha még nincs)
                                                    if (empty($get('share_token'))) {
                                                        $workSession = new \App\Models\WorkSession;
                                                        $token = $workSession->generateShareToken();
                                                        $set('share_token', $token);

                                                        // Generáljuk a publikus link értékét is
                                                        $url = config('app.frontend_url', config('app.url')).'/share/'.$token;
                                                        $set('share_link', $url);
                                                    }

                                                    // Ha nincs lejárat, állítsuk be 7 napra
                                                    if (empty($get('share_expires_at'))) {
                                                        $set('share_expires_at', now()->addDays(7));
                                                    }
                                                }
                                            })
                                            ->columnSpanFull(),

                                        Forms\Components\TextInput::make('share_link')
                                            ->label('Publikus link')
                                            ->readOnly()
                                            ->suffixIcon('heroicon-o-clipboard-document')
                                            ->extraInputAttributes([
                                                'x-on:click' => 'window.navigator.clipboard.writeText($el.value); $tooltip(\'Link vágólapra másolva!\', { timeout: 2000 })',
                                            ])
                                            ->visible(fn ($get, $record) => $get('share_enabled') && ($record?->share_token || $get('share_token')))
                                            ->helperText('Kattints a mezőre vagy az ikonra a link vágólapra másolásához')
                                            ->columnSpanFull()
                                            ->dehydrated(false),

                                        Forms\Components\TextInput::make('share_token')
                                            ->label('Megosztási Token')
                                            ->readOnly()
                                            ->visible(fn ($get, $record) => $get('share_enabled') && ($record !== null || $get('share_token')))
                                            ->helperText('Automatikusan generált egyedi token'),

                                        Forms\Components\DateTimePicker::make('share_expires_at')
                                            ->label('Link lejárati ideje')
                                            ->visible(fn ($get) => $get('share_enabled'))
                                            ->helperText('Ha üresen hagyod, 7 napig érvényes'),
                                    ])
                                    ->columns(2),

                                Section::make('Meghívások')
                                    ->description('Felhasználók meghívhatnak-e másokat')
                                    ->schema([
                                        Forms\Components\Toggle::make('allow_invitations')
                                            ->label('Meghívások engedélyezése')
                                            ->helperText('Ha be van kapcsolva, a felhasználók meghívhatnak másokat a munkamenetbe')
                                            ->default(false)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make('Kupon beállítások')
                            ->icon('heroicon-o-ticket')
                            ->visible(fn () => WorkSessionResource::canAccessTab('coupon-settings'))
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
                                    ->helperText('Válaszd ki, mely kuponok használhatók ennél a munkamenetnél. A munkamenet beállításai felülírják a csomag beállításait.'),
                            ])
                            ->columns(2),

                        Tabs\Tab::make('Árazás és Csomagok')
                            ->icon('heroicon-o-currency-dollar')
                            ->visible(fn ($get) => ! $get('is_tablo_mode') && WorkSessionResource::canAccessTab('pricing'))
                            ->schema([
                                Placeholder::make('priority_warning')
                                    ->content('⚠️ A munkamenet szintű beállítások felülírják az album szintű beállításokat!')
                                    ->columnSpanFull(),

                                Select::make('package_id')
                                    ->label('Csomag')
                                    ->relationship('package', 'name')
                                    ->searchable()
                                    ->nullable()
                                    ->helperText('Válaszd ki a csomagot ezen munkamenethez. A csomag korlátozza a képek számát és a mennyiség választókat elrejti.')
                                    ->preload()
                                    ->live(),

                                Select::make('price_list_id')
                                    ->label('Árlista')
                                    ->relationship('priceList', 'name')
                                    ->searchable()
                                    ->nullable()
                                    ->helperText('Válaszd ki az árlistát ezen munkamenethez.')
                                    ->visible(fn ($get) => ! $get('package_id'))
                                    ->preload(),
                            ])
                            ->columns(2),

                        Tabs\Tab::make('Tablófotózás')
                            ->icon('heroicon-o-photo')
                            ->visible(fn () => WorkSessionResource::canAccessTab('tablo-mode'))
                            ->schema([
                                Section::make()
                                    ->description('Speciális folyamat diákok számára: vendég belépés → képválogatás → automatikus regisztráció → retusálás → tablókép választás')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_tablo_mode')
                                            ->label('Tablófotózási mód')
                                            ->helperText('Ha be van kapcsolva, a csomag és árlista beállítások nem használhatók')
                                            ->live()
                                            ->afterStateUpdated(function ($state, $set, $get) {
                                                if ($state && empty($get('max_retouch_photos'))) {
                                                    $set('max_retouch_photos', 5);
                                                }
                                            })
                                            ->columnSpanFull(),

                                        Forms\Components\Radio::make('tablo_mode_type')
                                            ->label('Tablómód típusa')
                                            ->options(TabloModeType::class)
                                            ->descriptions([
                                                'fixed' => TabloModeType::FIXED->description(),
                                                'flexible' => TabloModeType::FLEXIBLE->description(),
                                                'packages' => TabloModeType::PACKAGES->description(),
                                            ])
                                            ->inline()
                                            ->visible(fn (Get $get) => $get('is_tablo_mode'))
                                            ->live()
                                            ->default('fixed')
                                            ->columnSpanFull(),

                                        Section::make('Fix retusálás beállítások')
                                            ->description('Pontos számú retusálható képek')
                                            ->visible(fn (Get $get) =>
                                                $get('is_tablo_mode') && $get('tablo_mode_type') === TabloModeType::FIXED
                                            )
                                            ->schema([
                                                Forms\Components\TextInput::make('max_retouch_photos')
                                                    ->label('Retusálható képek száma')
                                                    ->numeric()
                                                    ->minValue(5)
                                                    ->maxValue(50)
                                                    ->default(5)
                                                    ->required()
                                                    ->suffix('db')
                                                    ->helperText('Minden felhasználó pontosan ennyi képet retusáltathat'),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull(),

                                        Section::make('Rugalmas limit beállítások')
                                            ->description('Maximum limit + további képek egyéni áron')
                                            ->visible(fn (Get $get) =>
                                                $get('is_tablo_mode') && $get('tablo_mode_type') === TabloModeType::FLEXIBLE
                                            )
                                            ->schema([
                                                Forms\Components\TextInput::make('max_retouch_photos')
                                                    ->label('Maximum ingyenes retusálás')
                                                    ->numeric()
                                                    ->minValue(5)
                                                    ->default(5)
                                                    ->required()
                                                    ->suffix('db')
                                                    ->helperText('Ennyi képet választhat a felhasználó extra díj nélkül'),

                                                Forms\Components\Select::make('extra_photo_price_list_id')
                                                    ->label('További képek árlista')
                                                    ->relationship('extraPhotoPriceList', 'name')
                                                    ->searchable()
                                                    ->preload()
                                                    ->live()
                                                    ->helperText('Válaszd ki az árlistát a további képekhez')
                                                    ->afterStateHydrated(function (Forms\Components\Select $component, ?string $state) {
                                                        if (blank($state)) {
                                                            $defaultPriceList = \App\Models\PriceList::where('is_default', true)->first();
                                                            if ($defaultPriceList) {
                                                                $component->state($defaultPriceList->id);
                                                            }
                                                        }
                                                    })
                                                    ->afterStateUpdated(fn (callable $set) => $set('extra_photo_print_size_id', null)),

                                                Forms\Components\Select::make('extra_photo_print_size_id')
                                                    ->label('Alkalmazandó papírméret ár')
                                                    ->options(function (Get $get) {
                                                        $priceListId = $get('extra_photo_price_list_id');
                                                        if (!$priceListId) {
                                                            return [];
                                                        }

                                                        return Price::where('price_list_id', $priceListId)
                                                            ->with('printSize')
                                                            ->get()
                                                            ->pluck('printSize.name', 'print_size_id')
                                                            ->toArray();
                                                    })
                                                    ->searchable()
                                                    ->disabled(fn (Get $get) => !$get('extra_photo_price_list_id'))
                                                    ->helperText('Először válassz árlistát! Ez határozza meg az extra képek árát.')
                                                    ->required(fn (Get $get) => $get('extra_photo_price_list_id')),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull(),

                                        Section::make('Csomag alapú beállítások')
                                            ->description('Admin által kiválasztott 3-4 csomag')
                                            ->visible(fn (Get $get) =>
                                                $get('is_tablo_mode') && $get('tablo_mode_type') === TabloModeType::PACKAGES
                                            )
                                            ->schema([
                                                Forms\Components\Select::make('allowed_package_ids')
                                                    ->label('Választható csomagok')
                                                    ->options(fn () => Package::pluck('name', 'id')->toArray())
                                                    ->multiple()
                                                    ->searchable()
                                                    ->preload()
                                                    ->helperText('Válaszd ki a 3-4 csomagot, amelyek közül a felhasználók választhatnak')
                                                    ->minItems(3)
                                                    ->maxItems(4),

                                                Placeholder::make('package_info')
                                                    ->content('A felhasználók a frontend-en ezek közül a csomagok közül választhatnak majd. Az első csomag lesz az alapértelmezett.')
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull(),

                                        Placeholder::make('tablo_info')
                                            ->label('Tablófotózási folyamat lépései')
                                            ->content(new \Illuminate\Support\HtmlString('
                                                <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm text-gray-600 dark:text-gray-400">
                                                    <div>
                                                        <strong>1. Vendég belépés:</strong><br>
                                                        <span class="ml-4">Diákok belépnek digit kóddal</span>
                                                    </div>
                                                    <div>
                                                        <strong>2. Képválogatás:</strong><br>
                                                        <span class="ml-4">Minden diák kiválogatja saját képeit</span>
                                                    </div>
                                                    <div>
                                                        <strong>3. Regisztráció:</strong><br>
                                                        <span class="ml-4">Automatikus fiók létrehozás név, email, telefon megadással</span>
                                                    </div>
                                                    <div>
                                                        <strong>4. Retusálás választás:</strong><br>
                                                        <span class="ml-4">Kiválasztják, mely képeket retusáltassanak</span>
                                                    </div>
                                                    <div>
                                                        <strong>5. Tablókép választás:</strong><br>
                                                        <span class="ml-4">Egyetlen kép kiválasztása tablóra</span>
                                                    </div>
                                                    <div>
                                                        <strong>6. Véglegesítés:</strong><br>
                                                        <span class="ml-4">Minden lépés mentődik, nincs fizetés</span>
                                                    </div>
                                                </div>
                                            '))
                                            ->visible(false)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}

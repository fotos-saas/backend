<?php

namespace App\Filament\Resources\PartnerSettings;

use App\Filament\Resources\BaseResource;

use App\Filament\Resources\PartnerSettings\Pages\EditPartnerSetting;
use App\Filament\Resources\PartnerSettings\Pages\ListPartnerSettings;
use App\Models\PartnerSetting;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PartnerSettingResource extends BaseResource
{

    protected static function getPermissionKey(): string
    {
        return 'partner-settings';
    }
    protected static ?string $model = PartnerSetting::class;

    protected static ?string $navigationLabel = 'Partner beállítások';

    protected static ?string $modelLabel = 'Partner beállítás';

    protected static ?string $pluralModelLabel = 'Partner beállítások';

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Alap adatok')
                    ->columns(2)
                    ->components([
                        Forms\Components\TextInput::make('name')
                            ->label('Partner neve')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('slogan')
                            ->label('Szlogen')
                            ->maxLength(255),

                        Forms\Components\FileUpload::make('logo')
                            ->label('Logó')
                            ->image()
                            ->disk('public')
                            ->directory('partner-branding')
                            ->preserveFilenames()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '1:1',
                                '3:1',
                            ])
                            ->maxSize(2048)
                            ->hint('PNG vagy SVG, max. 2 MB.')
                            ->translateLabel(),

                        Forms\Components\FileUpload::make('favicon')
                            ->label('Favicon')
                            ->image()
                            ->disk('public')
                            ->directory('partner-branding')
                            ->preserveFilenames()
                            ->imagePreviewHeight('64')
                            ->maxSize(512)
                            ->hint('Négyzetes PNG/SVG, max. 512 KB.')
                            ->translateLabel(),

                        Forms\Components\TextInput::make('brand_color')
                            ->label('Brand szín')
                            ->placeholder('#FFAA00')
                            ->maxLength(7)
                            ->live(onBlur: true)
                            ->rule('regex:/^#?[0-9a-fA-F]{6}$/')
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Str::start($state, '#') : null)
                            ->translateLabel(),
                    ]),

                Section::make('Elérhetőségek')
                    ->columns(2)
                    ->components([
                        Forms\Components\TextInput::make('email')
                            ->label('E-mail cím')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->label('Telefonszám')
                            ->tel()
                            ->maxLength(64),

                        Forms\Components\Textarea::make('address')
                            ->label('Cím')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('tax_number')
                            ->label('Adószám')
                            ->maxLength(64),
                    ]),

                Section::make('Online jelenlét')
                    ->components([
                        Grid::make(2)
                            ->components([
                                Forms\Components\TextInput::make('website')
                                    ->label('Weboldal')
                                    ->url()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('landing_page_url')
                                    ->label('Landing oldal')
                                    ->url()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('instagram_url')
                                    ->label('Instagram')
                                    ->url()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('facebook_url')
                                    ->label('Facebook')
                                    ->url()
                                    ->maxLength(255),
                            ]),
                    ]),

                Section::make('Stripe Fizetési Beállítások')
                    ->description('Stripe API kulcsok az online fizetések kezeléséhez. A kulcsokat a Stripe Dashboard-ról szerezheted be.')
                    ->collapsible()
                    ->collapsed()
                    ->components([
                        Forms\Components\TextInput::make('stripe_secret_key')
                            ->label('Stripe Secret Key')
                            ->password()
                            ->revealable()
                            ->placeholder('sk_test_...')
                            ->helperText('Titkos API kulcs (Secret Key) - SOHA ne oszd meg senkivel!')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('stripe_public_key')
                            ->label('Stripe Public Key')
                            ->placeholder('pk_test_...')
                            ->helperText('Nyilvános API kulcs (Publishable Key) - ez látható a frontend oldalon')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('stripe_webhook_secret')
                            ->label('Stripe Webhook Secret')
                            ->password()
                            ->revealable()
                            ->placeholder('whsec_...')
                            ->helperText('Webhook aláírás kulcs - a webhook események hitelesítéséhez szükséges')
                            ->maxLength(255),
                    ])
                    ->columns(1),

                Section::make('Számlázási beállítások')
                    ->description('A számlázási rendszer beállításai')
                    ->components([
                        Forms\Components\Toggle::make('allow_company_invoicing')
                            ->label('Céges vásárlás engedélyezése')
                            ->helperText('Ha aktív, a vásárlók adószámmal és cégnévvel is vásárolhatnak')
                            ->default(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Partner neve')
                    ->weight('bold'),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->copyable(),

                TextColumn::make('phone')
                    ->label('Telefonszám'),

                BadgeColumn::make('is_active')
                    ->label('Aktív')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktív' : 'Inaktív')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make()
                    ->label('Szerkesztés'),
            ])
            ->bulkActions([
                // nincs bulk action
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('updated_at'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartnerSettings::route('/'),
            'edit' => EditPartnerSetting::route('/{record}/szerkesztes'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return PartnerSetting::query()->count() ? null : '!';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return PartnerSetting::query()->count() ? null : 'warning';
    }
}

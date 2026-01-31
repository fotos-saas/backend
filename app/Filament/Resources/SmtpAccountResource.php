<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SmtpAccountResource\Pages;
use App\Models\SmtpAccount;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;

class SmtpAccountResource extends BaseResource
{

    protected static function getPermissionKey(): string
    {
        return 'smtp-accounts';
    }
    protected static ?string $model = SmtpAccount::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'SMTP Fiókok';

    protected static ?string $modelLabel = 'SMTP Fiók';

    protected static ?string $pluralModelLabel = 'SMTP Fiókok';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return 'Email Rendszer';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->tabs([
                        Tabs\Tab::make('Alapadatok')
                            ->icon('heroicon-o-information-circle')
                            ->components([
                                Grid::make(2)
                                    ->components([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Fiók neve')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('pl. Gmail Prod, AWS SES Dev'),

                                        Forms\Components\Select::make('mailer_type')
                                            ->label('Mail típus')
                                            ->options([
                                                'smtp' => 'SMTP',
                                                'ses' => 'Amazon SES',
                                                'postmark' => 'Postmark',
                                                'sendmail' => 'Sendmail',
                                                'mailgun' => 'Mailgun',
                                            ])
                                            ->default('smtp')
                                            ->required(),
                                    ]),

                                Grid::make(3)
                                    ->components([
                                        Forms\Components\Toggle::make('is_prod')
                                            ->label('Produkciós környezet')
                                            ->helperText('Prod vagy dev környezethez'),

                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Aktív')
                                            ->helperText('Csak 1 lehet aktív környezetenként')
                                            ->default(true),

                                        Forms\Components\TextInput::make('priority')
                                            ->label('Prioritás')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(10)
                                            ->default(5)
                                            ->helperText('1 = legmagasabb, 10 = legalacsonyabb')
                                            ->required(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Kapcsolat')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->components([
                                Grid::make(2)
                                    ->components([
                                        Forms\Components\TextInput::make('host')
                                            ->label('SMTP Host')
                                            ->maxLength(255)
                                            ->placeholder('smtp.gmail.com'),

                                        Forms\Components\TextInput::make('port')
                                            ->label('Port')
                                            ->numeric()
                                            ->default(587),
                                    ]),

                                Grid::make(2)
                                    ->components([
                                        Forms\Components\TextInput::make('username')
                                            ->label('Felhasználónév')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('password')
                                            ->label('Jelszó')
                                            ->password()
                                            ->maxLength(255)
                                            ->dehydrated(fn ($state) => filled($state))
                                            ->helperText('Hagyd üresen, ha nem szeretnéd megváltoztatni'),
                                    ]),

                                Forms\Components\Select::make('encryption')
                                    ->label('Titkosítás')
                                    ->options([
                                        'tls' => 'TLS',
                                        'ssl' => 'SSL',
                                        '' => 'Nincs',
                                    ])
                                    ->default('tls'),
                            ]),

                        Tabs\Tab::make('Feladó')
                            ->icon('heroicon-o-user')
                            ->components([
                                Forms\Components\TextInput::make('from_address')
                                    ->label('Feladó email cím')
                                    ->email()
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('from_name')
                                    ->label('Feladó név')
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Tabs\Tab::make('Korlátozás')
                            ->icon('heroicon-o-clock')
                            ->components([
                                Grid::make(2)
                                    ->components([
                                        Forms\Components\TextInput::make('rate_limit_per_minute')
                                            ->label('Maximum / perc')
                                            ->numeric()
                                            ->minValue(0)
                                            ->helperText('Percenkénti email limit (üres = korlátlan)'),

                                        Forms\Components\TextInput::make('rate_limit_per_hour')
                                            ->label('Maximum / óra')
                                            ->numeric()
                                            ->minValue(0)
                                            ->helperText('Óránkénti email limit (üres = korlátlan)'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Biztonság')
                            ->icon('heroicon-o-shield-check')
                            ->components([
                                Section::make('DKIM Beállítások')
                                    ->description('DomainKeys Identified Mail - email hitelesítés (opcionális)')
                                    ->collapsible()
                                    ->collapsed(true)
                                    ->components([
                                        Grid::make(2)
                                            ->components([
                                                Forms\Components\TextInput::make('dkim_domain')
                                                    ->label('DKIM Domain')
                                                    ->maxLength(255)
                                                    ->placeholder('example.com'),

                                                Forms\Components\TextInput::make('dkim_selector')
                                                    ->label('DKIM Selector')
                                                    ->maxLength(255)
                                                    ->placeholder('default'),
                                            ]),

                                        Forms\Components\Textarea::make('dkim_private_key')
                                            ->label('DKIM Private Key')
                                            ->rows(5)
                                            ->helperText('PEM formátumú privát kulcs'),
                                    ]),

                                Section::make('SPF és DMARC')
                                    ->description('Spam védelem kiegészítő beállítások (opcionális)')
                                    ->collapsible()
                                    ->collapsed(true)
                                    ->components([
                                        Forms\Components\TextInput::make('dmarc_policy')
                                            ->label('DMARC Policy')
                                            ->maxLength(255)
                                            ->placeholder('v=DMARC1; p=quarantine;'),

                                        Forms\Components\Textarea::make('spf_record')
                                            ->label('SPF Record jegyzet')
                                            ->helperText('Csak emlékeztetőnek, nem alkalmazzuk automatikusan')
                                            ->rows(3),

                                        Forms\Components\TextInput::make('bounce_email')
                                            ->label('Bounce Email cím')
                                            ->email()
                                            ->maxLength(255)
                                            ->helperText('Visszapattanó emailek fogadója'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Átirányítás')
                            ->icon('heroicon-o-arrow-path')
                            ->components([
                                Forms\Components\Select::make('manual_redirect_to')
                                    ->label('Átirányítás másik SMTP-re')
                                    ->relationship('redirectTarget', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Ha ez be van állítva, az emailek ezen keresztül mennek ki'),
                            ]),

                        Tabs\Tab::make('IMAP')
                            ->icon('heroicon-o-inbox-arrow-down')
                            ->components([
                                Forms\Components\Toggle::make('imap_save_sent')
                                    ->label('Elküldött emailek mentése IMAP-ra')
                                    ->helperText('Ha be van kapcsolva, az elküldött emailek a Sent mappába kerülnek')
                                    ->live(),

                                Section::make('IMAP Kapcsolat')
                                    ->description('IMAP beállítások az elküldött emailek mentéséhez')
                                    ->visible(fn (Get $get): bool => (bool) $get('imap_save_sent'))
                                    ->components([
                                        Grid::make(2)
                                            ->components([
                                                Forms\Components\TextInput::make('imap_host')
                                                    ->label('IMAP Host')
                                                    ->maxLength(255)
                                                    ->placeholder('imap.gmail.com')
                                                    ->required(fn (Get $get): bool => (bool) $get('imap_save_sent')),

                                                Forms\Components\TextInput::make('imap_port')
                                                    ->label('IMAP Port')
                                                    ->numeric()
                                                    ->default(993),
                                            ]),

                                        Grid::make(2)
                                            ->components([
                                                Forms\Components\TextInput::make('imap_username')
                                                    ->label('IMAP Felhasználónév')
                                                    ->maxLength(255)
                                                    ->placeholder('user@domain.com')
                                                    ->required(fn (Get $get): bool => (bool) $get('imap_save_sent')),

                                                Forms\Components\TextInput::make('imap_password')
                                                    ->label('IMAP Jelszó')
                                                    ->password()
                                                    ->maxLength(255)
                                                    ->dehydrated(fn ($state) => filled($state))
                                                    ->helperText('Hagyd üresen, ha nem szeretnéd megváltoztatni'),
                                            ]),

                                        Grid::make(2)
                                            ->components([
                                                Forms\Components\Select::make('imap_encryption')
                                                    ->label('Titkosítás')
                                                    ->options([
                                                        'ssl' => 'SSL',
                                                        'tls' => 'TLS',
                                                        '' => 'Nincs',
                                                    ])
                                                    ->default('ssl'),

                                                Forms\Components\TextInput::make('imap_sent_folder')
                                                    ->label('Sent mappa neve')
                                                    ->maxLength(255)
                                                    ->default('Sent')
                                                    ->helperText('Általában: Sent, INBOX.Sent, Sent Items'),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Állapot')
                            ->icon('heroicon-o-heart')
                            ->components([
                                Forms\Components\Select::make('health_status')
                                    ->label('Health státusz')
                                    ->options([
                                        'healthy' => 'Egészséges',
                                        'warning' => 'Figyelmeztetés',
                                        'error' => 'Hiba',
                                        'unchecked' => 'Nem ellenőrzött',
                                    ])
                                    ->default('unchecked')
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\DateTimePicker::make('last_health_check_at')
                                    ->label('Utolsó health check')
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\Textarea::make('health_error_message')
                                    ->label('Hiba üzenet')
                                    ->rows(3)
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Név')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (SmtpAccount $record): string => $record->from_address),

                Tables\Columns\TextColumn::make('mailer_type')
                    ->label('Típus')
                    ->badge()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_prod')
                    ->label('Környezet')
                    ->boolean()
                    ->trueIcon('heroicon-o-rocket-launch')
                    ->falseIcon('heroicon-o-code-bracket')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->sortable()
                    ->tooltip(fn (SmtpAccount $record): string => $record->is_prod ? 'Production' : 'Development'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktív')
                    ->sortable(),

                Tables\Columns\TextColumn::make('health_status')
                    ->label('Állapot')
                    ->badge()
                    ->colors([
                        'success' => 'healthy',
                        'warning' => 'warning',
                        'danger' => 'error',
                        'gray' => 'unchecked',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'healthy' => '✓ OK',
                        'warning' => '⚠ Figyelem',
                        'error' => '✗ Hiba',
                        'unchecked' => '— Nincs',
                        default => $state,
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_prod')
                    ->label('Prod környezet'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktív'),

                Tables\Filters\SelectFilter::make('health_status')
                    ->label('Health státusz')
                    ->options([
                        'healthy' => 'Egészséges',
                        'warning' => 'Figyelmeztetés',
                        'error' => 'Hiba',
                        'unchecked' => 'Nem ellenőrzött',
                    ]),
            ])
            ->actions([
                EditAction::make(),

                Action::make('test_email')
                    ->label('Teszt Email')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->form([
                        Forms\Components\TextInput::make('recipient')
                            ->label('Címzett')
                            ->email()
                            ->required()
                            ->default(fn () => config('mail.test_recipient') ?? config('mail.from.address')),
                    ])
                    ->action(function (SmtpAccount $record, array $data) {
                        try {
                            $subject = "Teszt Email - {$record->name}";
                            $body = "<h3>SMTP Teszt Email</h3>
                                     <p>Ez egy teszt email.</p>
                                     <p><strong>SMTP Fiók:</strong> {$record->name}</p>
                                     <p><strong>Küldés ideje:</strong> ".now()->format('Y-m-d H:i:s').'</p>';

                            $mailerName = $record->getDynamicMailerName();
                            Mail::mailer($mailerName)
                                ->to($data['recipient'])
                                ->send(new \App\Mail\TemplateMail($subject, $body, []));

                            // Save to IMAP Sent folder if configured
                            if ($record->canSaveToSent()) {
                                $record->saveToSentFolder($data['recipient'], $subject, $body);
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Teszt email elküldve!')
                                ->body("Címzett: {$data['recipient']}")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Teszt email küldése sikertelen!')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('health_check')
                    ->label('Health Check')
                    ->icon('heroicon-o-heart')
                    ->color('success')
                    ->action(function (SmtpAccount $record) {
                        \Artisan::call('smtp:health-check', ['--smtp-account-id' => $record->id]);

                        \Filament\Notifications\Notification::make()
                            ->title('Health check lefuttatva!')
                            ->body('Frissítsd az oldalt az eredményhez')
                            ->success()
                            ->send();
                    }),

                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority', 'asc')
            ->modifyQueryUsing(function ($query) {
                $newRecordId = session('new_smtp_account_id');
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
            'index' => Pages\ListSmtpAccounts::route('/'),
            'create' => Pages\CreateSmtpAccount::route('/create'),
            'edit' => Pages\EditSmtpAccount::route('/{record}/edit'),
        ];
    }
}

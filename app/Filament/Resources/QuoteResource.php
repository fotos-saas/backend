<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuoteResource\Pages;
use App\Models\Quote;
use App\Models\SmtpAccount;
use App\Models\TabloEmailSnippet;
use App\Services\QuoteService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;

class QuoteResource extends BaseResource
{
    protected static function getPermissionKey(): string
    {
        return 'quotes';
    }

    protected static ?string $model = Quote::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Árajánlatok';

    protected static ?string $modelLabel = 'Árajánlat';

    protected static ?string $pluralModelLabel = 'Árajánlatok';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return 'Tabló Rendszer';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->tabs([
                        // 1. TAB: Alapadatok
                        Tabs\Tab::make('Alapadatok')
                            ->icon('heroicon-o-information-circle')
                            ->components([
                                Grid::make(2)
                                    ->components([
                                        Forms\Components\TextInput::make('customer_name')
                                            ->label('Ügyfél neve')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Kovács János'),

                                        Forms\Components\TextInput::make('customer_title')
                                            ->label('Megszólítás')
                                            ->maxLength(255)
                                            ->placeholder('Tisztelt Kovács Úr!')
                                            ->helperText('Pl. "Tisztelt Kovács Úr!" vagy "Kedves Mária!"'),
                                    ]),

                                Grid::make(2)
                                    ->components([
                                        Forms\Components\TextInput::make('customer_email')
                                            ->label('Email cím')
                                            ->email()
                                            ->maxLength(255)
                                            ->placeholder('ugyfel@example.com'),

                                        Forms\Components\TextInput::make('customer_phone')
                                            ->label('Telefonszám')
                                            ->tel()
                                            ->maxLength(255)
                                            ->placeholder('+36 30 123 4567'),
                                    ]),

                                Grid::make(2)
                                    ->components([
                                        Forms\Components\Select::make('quote_category')
                                            ->label('Árajánlat kategória')
                                            ->options([
                                                'custom' => 'Egyedi (szabad szöveges)',
                                                'photographer' => 'Fotós (strukturált árlista)',
                                            ])
                                            ->required()
                                            ->default('custom')
                                            ->native(false)
                                            ->live(),

                                        Forms\Components\Select::make('quote_type')
                                            ->label('Típus')
                                            ->options([
                                                'repro' => 'Repro',
                                                'full_production' => 'Teljes kivitelezés',
                                                'digital' => 'Digitális',
                                            ])
                                            ->required()
                                            ->default('full_production')
                                            ->native(false)
                                            ->visible(fn (Get $get): bool => $get('quote_category') === 'custom'),
                                    ]),

                                Grid::make(3)
                                    ->components([
                                        Forms\Components\DatePicker::make('quote_date')
                                            ->label('Árajánlat dátuma')
                                            ->required()
                                            ->default(now())
                                            ->displayFormat('Y. m. d.')
                                            ->native(false),

                                        Forms\Components\TextInput::make('quote_number')
                                            ->label('Árajánlat száma')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255)
                                            ->default(fn () => Quote::generateQuoteNumber())
                                            ->placeholder('AJ-2026-001')
                                            ->helperText('Automatikusan generált'),

                                        Forms\Components\TextInput::make('size')
                                            ->label('Méret')
                                            ->maxLength(255)
                                            ->placeholder('43x65, 50x70, Repro')
                                            ->helperText('Pl. "43x65", "50x70" vagy "Repro"'),
                                    ]),

                                Section::make('Árazás')
                                    ->description('Árak forintban (Ft)')
                                    ->collapsible()
                                    ->collapsed(false)
                                    ->visible(fn (Get $get): bool => $get('quote_category') === 'custom')
                                    ->components([
                                        Grid::make(2)
                                            ->components([
                                                Forms\Components\TextInput::make('base_price')
                                                    ->label('Alap ár')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->suffix('Ft')
                                                    ->minValue(0)
                                                    ->maxValue(99999999)
                                                    ->required(),

                                                Forms\Components\TextInput::make('discount_price')
                                                    ->label('Kedvezményes ár')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->suffix('Ft')
                                                    ->minValue(0)
                                                    ->maxValue(99999999)
                                                    ->helperText('Ha 0, akkor nincs kedvezmény'),
                                            ]),
                                    ]),
                            ]),

                        // 2. TAB: Tartalom
                        Tabs\Tab::make('Tartalom')
                            ->icon('heroicon-o-document-text')
                            ->components([
                                // === EGYEDI KATEGÓRIA ===
                                Section::make('Egyedi tartalom')
                                    ->visible(fn (Get $get): bool => $get('quote_category') === 'custom')
                                    ->components([
                                        Forms\Components\Placeholder::make('available_variables')
                                            ->label('Elérhető változók')
                                            ->content(new HtmlString(
                                                '<div style="background: #f3f4f6; padding: 12px; border-radius: 6px; font-size: 12px;">'.
                                                '<strong>Használható placeholder-ek:</strong><br>'.
                                                implode(' • ', array_keys(Quote::getAvailableVariables())).
                                                '</div>'
                                            )),

                                        Forms\Components\Textarea::make('intro_text')
                                            ->label('Bevezető szöveg')
                                            ->rows(5)
                                            ->placeholder('{{customer_title}}\n\nKöszönjük érdeklődését a {{size}} méretű tablókép készítése iránt...')
                                            ->helperText('Használhatod a fenti placeholder-eket a szövegben'),

                                        Forms\Components\Repeater::make('content_items')
                                            ->label('Tartalmi pontok')
                                            ->schema([
                                                Forms\Components\TextInput::make('title')
                                                    ->label('Cím')
                                                    ->required()
                                                    ->placeholder('Pl. "Első egyeztetés"'),

                                                Forms\Components\Textarea::make('description')
                                                    ->label('Leírás')
                                                    ->rows(3)
                                                    ->placeholder('Részletes leírás erről a pontról...'),
                                            ])
                                            ->columns(1)
                                            ->collapsed(false)
                                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                                            ->addActionLabel('Új pont hozzáadása')
                                            ->reorderable()
                                            ->collapsible()
                                            ->defaultItems(0),
                                    ]),

                                // === FOTÓS KATEGÓRIA ===
                                Section::make('Fotós árlista')
                                    ->visible(fn (Get $get): bool => $get('quote_category') === 'photographer')
                                    ->description('Tabló méretek, árak és mennyiségi kedvezmények')
                                    ->components([
                                        Forms\Components\Textarea::make('intro_text')
                                            ->label('Bevezető szöveg')
                                            ->rows(3)
                                            ->placeholder('Kedves Fotós Kolléga! Az alábbiakban küldöm az aktuális tablóárakról szóló tájékoztatót...'),

                                        Forms\Components\Repeater::make('price_list_items')
                                            ->label('Tabló méretek és árak')
                                            ->schema([
                                                Forms\Components\TextInput::make('size')
                                                    ->label('Méret kód')
                                                    ->required()
                                                    ->placeholder('50x70'),

                                                Forms\Components\TextInput::make('label')
                                                    ->label('Leírás')
                                                    ->required()
                                                    ->placeholder('50x70 cm keretezett tabló'),

                                                Forms\Components\TextInput::make('price')
                                                    ->label('Ár')
                                                    ->numeric()
                                                    ->required()
                                                    ->suffix('Ft')
                                                    ->mask(RawJs::make('$money($input, \' \', \'.\', 0)')),
                                            ])
                                            ->columns(3)
                                            ->defaultItems(4)
                                            ->reorderable()
                                            ->collapsible()
                                            ->itemLabel(fn (array $state): ?string =>
                                                isset($state['size'], $state['price'])
                                                    ? "{$state['size']} - " . number_format((int) $state['price'], 0, ',', ' ') . ' Ft'
                                                    : null
                                            )
                                            ->addActionLabel('Új méret hozzáadása'),

                                        Forms\Components\Repeater::make('volume_discounts')
                                            ->label('Mennyiségi kedvezmények')
                                            ->schema([
                                                Forms\Components\TextInput::make('minQty')
                                                    ->label('Minimum db')
                                                    ->numeric()
                                                    ->required()
                                                    ->minValue(1),

                                                Forms\Components\TextInput::make('percentOff')
                                                    ->label('Kedvezmény')
                                                    ->numeric()
                                                    ->required()
                                                    ->suffix('%')
                                                    ->minValue(0)
                                                    ->maxValue(100),

                                                Forms\Components\TextInput::make('label')
                                                    ->label('Megjelenítés')
                                                    ->placeholder('5+ db'),
                                            ])
                                            ->columns(3)
                                            ->defaultItems(3)
                                            ->default([
                                                ['minQty' => 5, 'percentOff' => 5, 'label' => '5+ db'],
                                                ['minQty' => 10, 'percentOff' => 10, 'label' => '10+ db'],
                                                ['minQty' => 20, 'percentOff' => 15, 'label' => '20+ db'],
                                            ])
                                            ->collapsible()
                                            ->collapsed()
                                            ->addActionLabel('Új kedvezménysáv'),

                                        Forms\Components\Textarea::make('notes')
                                            ->label('Egyéb megjegyzések')
                                            ->rows(3)
                                            ->placeholder('További információk, megjegyzések...'),
                                    ]),
                            ]),

                        // 3. TAB: Beállítások (csak Egyedi kategóriánál)
                        Tabs\Tab::make('Beállítások')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->visible(fn (Get $get): bool => $get('quote_category') === 'custom')
                            ->components([
                                Section::make('Opciók')
                                    ->description('További szolgáltatások és beállítások')
                                    ->components([
                                        Forms\Components\Toggle::make('is_full_execution')
                                            ->label('Teljes kivitelezés')
                                            ->helperText('Teljes tabló kivitelezés (fotózás + nyomtatás)')
                                            ->default(false)
                                            ->inline(false),

                                        Forms\Components\Toggle::make('has_small_tablo')
                                            ->label('Kistabló hozzáadása')
                                            ->helperText('Van kistabló a csomagban')
                                            ->default(false)
                                            ->inline(false)
                                            ->live(),

                                        Forms\Components\Toggle::make('has_production')
                                            ->label('Kivitelezés (nyomtatás + keretezés)')
                                            ->helperText('Nyomtatás és keretezés költsége')
                                            ->default(false)
                                            ->inline(false)
                                            ->live(),

                                        Forms\Components\Toggle::make('has_shipping')
                                            ->label('Szállítás')
                                            ->helperText('Szállítási költség hozzáadása')
                                            ->default(false)
                                            ->inline(false)
                                            ->live(),
                                    ]),

                                Section::make('Extra árak')
                                    ->description('Opcionális költségek')
                                    ->collapsible()
                                    ->collapsed(false)
                                    ->visible(fn (Get $get): bool => $get('has_small_tablo') || $get('has_production') || $get('has_shipping'))
                                    ->components([
                                        Grid::make(3)
                                            ->components([
                                                Forms\Components\TextInput::make('small_tablo_price')
                                                    ->label('Kistabló ár')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->suffix('Ft')
                                                    ->minValue(0)
                                                    ->visible(fn (Get $get): bool => (bool) $get('has_small_tablo')),

                                                Forms\Components\TextInput::make('production_price')
                                                    ->label('Kivitelezés ár')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->suffix('Ft')
                                                    ->minValue(0)
                                                    ->visible(fn (Get $get): bool => (bool) $get('has_production')),

                                                Forms\Components\TextInput::make('shipping_price')
                                                    ->label('Szállítási díj')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->suffix('Ft')
                                                    ->minValue(0)
                                                    ->visible(fn (Get $get): bool => (bool) $get('has_shipping')),
                                            ]),
                                    ]),

                                Section::make('Kiegészítő szövegek')
                                    ->description('További megjegyzések és magyarázatok')
                                    ->collapsible()
                                    ->collapsed(true)
                                    ->components([
                                        Forms\Components\Textarea::make('small_tablo_text')
                                            ->label('Kistabló szöveg')
                                            ->rows(3)
                                            ->placeholder('A kistabló tartalmazza...')
                                            ->visible(fn (Get $get): bool => (bool) $get('has_small_tablo')),

                                        Forms\Components\Textarea::make('production_text')
                                            ->label('Kivitelezés szöveg')
                                            ->rows(3)
                                            ->placeholder('A kivitelezés tartalmazza...')
                                            ->visible(fn (Get $get): bool => (bool) $get('has_production')),

                                        Forms\Components\Textarea::make('discount_text')
                                            ->label('Kedvezmény magyarázat')
                                            ->rows(3)
                                            ->placeholder('A kedvezmény oka...'),

                                        Forms\Components\Textarea::make('notes')
                                            ->label('Egyéb megjegyzések')
                                            ->rows(3)
                                            ->placeholder('További információk...'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('quote_number')
                    ->label('Árajánlat száma')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Árajánlat szám másolva!'),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Ügyfél')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Quote $record): string => $record->quote_date->format('Y. m. d.')),

                Tables\Columns\TextColumn::make('quote_category')
                    ->label('Kategória')
                    ->badge()
                    ->colors([
                        'info' => 'custom',
                        'success' => 'photographer',
                    ])
                    ->formatStateUsing(fn (Quote $record): string => $record->getQuoteCategoryLabel())
                    ->sortable(),

                Tables\Columns\TextColumn::make('quote_type')
                    ->label('Típus')
                    ->badge()
                    ->colors([
                        'success' => 'full_production',
                        'info' => 'repro',
                        'warning' => 'digital',
                    ])
                    ->formatStateUsing(fn (Quote $record): string => $record->getQuoteTypeLabel())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('size')
                    ->label('Méret')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('Teljes ár')
                    ->money('HUF')
                    ->getStateUsing(fn (Quote $record): int => $record->calculateTotalPrice())
                    ->weight('bold')
                    ->color('success')
                    ->sortable(query: function ($query, string $direction): void {
                        // Custom sorting a kalkulált mező alapján
                    }),

                Tables\Columns\TextColumn::make('quote_date')
                    ->label('Dátum')
                    ->date('Y. m. d.')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y. m. d. H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('quote_type')
                    ->label('Típus')
                    ->options([
                        'repro' => 'Repro',
                        'full_production' => 'Teljes kivitelezés',
                        'digital' => 'Digitális',
                    ])
                    ->native(false),

                Tables\Filters\Filter::make('quote_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Dátum -tól'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Dátum -ig'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('quote_date', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('quote_date', '<=', $data['until']));
                    }),
            ])
            ->actions([
                // PDF előnézet (inline, új lapon)
                Action::make('preview_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->url(fn (Quote $record): string => route('admin.quotes.pdf', $record))
                    ->openUrlInNewTab()
                    ->tooltip('PDF megnyitása új lapon'),

                // Email küldés modal
                Action::make('send_email')
                    ->label('Email')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->form([
                        Grid::make(2)
                            ->components([
                                Forms\Components\Select::make('smtp_account_id')
                                    ->label('SMTP fiók')
                                    ->options(function (): array {
                                        return SmtpAccount::query()
                                            ->where('is_active', true)
                                            ->pluck('name', 'id')
                                            ->toArray();
                                    })
                                    ->required()
                                    ->default(function (): ?int {
                                        return SmtpAccount::where('is_active', true)->first()?->id;
                                    })
                                    ->searchable()
                                    ->native(false),

                                Forms\Components\Select::make('email_template_id')
                                    ->label('Sablon')
                                    ->options(function (): array {
                                        return TabloEmailSnippet::query()
                                            ->active()
                                            ->ordered()
                                            ->pluck('name', 'id')
                                            ->toArray();
                                    })
                                    ->placeholder('Válassz sablont...')
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Quote $record) {
                                        if ($state) {
                                            $template = TabloEmailSnippet::find($state);
                                            if ($template) {
                                                $data = [
                                                    'nev' => $record->customer_name,
                                                    'osztaly' => '',
                                                    'iskola' => '',
                                                    'ev' => '',
                                                    'quote_number' => $record->quote_number,
                                                ];
                                                $subject = $template->renderSubject($data);
                                                $body = $template->renderContent($data);

                                                // Extra quote-specifikus változók cseréje
                                                $subject = str_replace('{quote_number}', $record->quote_number, $subject ?? '');
                                                $body = str_replace('{quote_number}', $record->quote_number, $body ?? '');

                                                if ($subject) {
                                                    $set('subject', $subject);
                                                }
                                                $set('body', $body);
                                            }
                                        }
                                    }),
                            ]),

                        Forms\Components\TextInput::make('to_email')
                            ->label('Címzett email')
                            ->email()
                            ->required()
                            ->default(fn (Quote $record): ?string => $record->customer_email)
                            ->placeholder('ugyfel@example.com'),

                        Forms\Components\TextInput::make('subject')
                            ->label('Tárgy')
                            ->required()
                            ->default(fn (Quote $record): string => "Árajánlat - {$record->quote_number}")
                            ->placeholder('Árajánlat - AJ-2026-001'),

                        Forms\Components\RichEditor::make('body')
                            ->label('Üzenet szövege')
                            ->required()
                            ->default(function (Quote $record): string {
                                return "<p>Kedves {$record->customer_name}!</p>".
                                    "<p>Mellékletben küldöm az árajánlatot ({$record->quote_number}).</p>".
                                    "<p>Kérem, ha bármilyen kérdése van, keressen bizalommal!</p>".
                                    "<p>--<br>Üdvözlettel:<br><strong>Nové Ferenc</strong><br>Ügyvezető<br>tablokiraly.hu</p>";
                            })
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'link',
                                'bulletList',
                                'orderedList',
                            ]),

                        Forms\Components\FileUpload::make('extra_attachments')
                            ->label('További csatolmányok')
                            ->multiple()
                            ->maxFiles(5)
                            ->maxSize(10240) // 10MB
                            ->preserveFilenames()
                            ->directory('temp/email-attachments')
                            ->helperText('Maximum 5 fájl, egyenként max 10MB. Az árajánlat PDF automatikusan csatolva lesz.'),
                    ])
                    ->modalHeading('Email küldés')
                    ->modalDescription('Az árajánlat PDF-et automatikusan csatoljuk az emailhez.')
                    ->modalSubmitActionLabel('Küldés')
                    ->modalWidth('xl')
                    ->action(function (Quote $record, array $data): void {
                        try {
                            $smtpAccount = SmtpAccount::findOrFail($data['smtp_account_id']);
                            $effectiveAccount = $smtpAccount->getEffectiveAccount();
                            $mailerName = $effectiveAccount->getDynamicMailerName();

                            // PDF generálás
                            $quoteService = app(QuoteService::class);
                            $pdfContent = $quoteService->generatePdf($record);
                            $pdfFilename = "arajanlat-{$record->quote_number}.pdf";

                            // Csatolmányok összeállítása (PDF + extra fájlok)
                            $attachments = [
                                [
                                    'content' => $pdfContent,
                                    'filename' => $pdfFilename,
                                    'mime' => 'application/pdf',
                                ],
                            ];

                            // Extra csatolmányok feldolgozása
                            $extraAttachments = $data['extra_attachments'] ?? [];
                            foreach ($extraAttachments as $path) {
                                $fullPath = storage_path('app/public/' . $path);
                                if (file_exists($fullPath)) {
                                    $attachments[] = [
                                        'content' => file_get_contents($fullPath),
                                        'filename' => basename($path),
                                        'mime' => mime_content_type($fullPath) ?: 'application/octet-stream',
                                    ];
                                }
                            }

                            // Email küldés (body már HTML a RichEditor-ból)
                            Mail::mailer($mailerName)->send([], [], function ($message) use ($data, $attachments) {
                                $message->to($data['to_email'])
                                    ->subject($data['subject'])
                                    ->html($data['body']);

                                // Csatolmányok hozzáadása
                                foreach ($attachments as $attachment) {
                                    $message->attachData($attachment['content'], $attachment['filename'], [
                                        'mime' => $attachment['mime'],
                                    ]);
                                }
                            });

                            // IMAP save to sent (ha engedélyezve) - minden csatolmánnyal együtt
                            if ($effectiveAccount->canSaveToSent()) {
                                $effectiveAccount->saveToSentFolder(
                                    $data['to_email'],
                                    $data['subject'],
                                    $data['body'],
                                    [],
                                    $attachments
                                );
                            }

                            // Temp fájlok törlése
                            foreach ($extraAttachments as $path) {
                                $fullPath = storage_path('app/public/' . $path);
                                if (file_exists($fullPath)) {
                                    @unlink($fullPath);
                                }
                            }

                            Notification::make()
                                ->title('Email elküldve')
                                ->body("Az árajánlat sikeresen elküldve: {$data['to_email']}")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            \Log::error('Quote email send failed', [
                                'quote_id' => $record->id,
                                'error' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->title('Hiba történt')
                                ->body('Nem sikerült elküldeni az emailt: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // More dropdown
                ActionGroup::make([
                    EditAction::make()
                        ->label('Szerkesztés'),

                    Action::make('download_pdf')
                        ->label('PDF letöltés')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->url(fn (Quote $record): string => route('admin.quotes.download', $record))
                        ->openUrlInNewTab(),

                    Action::make('duplicate')
                        ->label('Duplikálás')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Árajánlat duplikálása')
                        ->modalDescription('Biztosan duplikálni szeretnéd ezt az árajánlatot? Az új árajánlat új számot kap.')
                        ->modalSubmitActionLabel('Duplikálás')
                        ->action(function (Quote $record) {
                            $newQuote = $record->replicate();
                            $newQuote->quote_number = Quote::generateQuoteNumber();
                            $newQuote->quote_date = now();
                            $newQuote->created_at = now();
                            $newQuote->updated_at = now();
                            $newQuote->save();

                            session()->put('new_quote_id', $newQuote->id);

                            Notification::make()
                                ->title('Árajánlat duplikálva')
                                ->body("Új árajánlat létrehozva: {$newQuote->quote_number}")
                                ->success()
                                ->send();

                            return redirect(QuoteResource::getUrl('edit', ['record' => $newQuote]));
                        }),

                    DeleteAction::make()
                        ->label('Törlés'),
                ])
                    ->label('')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('gray')
                    ->tooltip('További műveletek'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('quote_date', 'desc')
            ->modifyQueryUsing(function ($query) {
                $newRecordId = session('new_quote_id');
                if ($newRecordId) {
                    return $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$newRecordId])
                        ->orderBy('quote_date', 'desc');
                }

                return $query->orderBy('quote_date', 'desc');
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
            'index' => Pages\ListQuotes::route('/'),
            'create' => Pages\CreateQuote::route('/create'),
            'edit' => Pages\EditQuote::route('/{record}/edit'),
        ];
    }
}

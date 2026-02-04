<?php

namespace App\Filament\Resources;

use App\Enums\TabloProjectStatus;
use App\Filament\Concerns\HasUnaccentSearch;
use App\Filament\Resources\TabloProjectResource\Pages;
use App\Filament\Resources\TabloProjectResource\RelationManagers;
use App\Filament\Resources\TabloProjectResource\Widgets;
use App\Filament\Schemas\AccessCodeSection;
use App\Filament\Schemas\AdminPreviewSection;
use App\Filament\Schemas\ShareTokenSection;
use App\Models\TabloProject;
use App\Services\ClaudeService;
use App\Services\TabloApiService;
use BackedEnum;
use UnitEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TabloProjectResource extends BaseResource
{
    use HasUnaccentSearch;

    /**
     * Kereshető oszlopok ékezet- és kis/nagybetű-független kereséssel.
     */
    protected static array $unaccentSearchColumns = [
        'school.name',        // Iskola neve
        'class_name',         // Osztály neve
        'contacts.name',      // Kapcsolattartó neve
        'contacts.phone',     // Telefonszám
        'contacts.email',     // Email
        'partner.name',       // Partner neve
    ];

    protected static ?string $model = TabloProject::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $navigationLabel = 'Tablók';

    protected static ?string $modelLabel = 'Tabló';

    protected static ?string $pluralModelLabel = 'Tablók';

    protected static string | UnitEnum | null $navigationGroup = 'Tabló';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        $isAdmin = function () {
            $user = auth()->user();
            return ! ($user && $user->hasRole('tablo') && ! $user->hasAnyRole(['super_admin', 'photo_admin']));
        };

        return $schema
            ->components([
                Tabs::make('Tabló Projekt')
                    ->tabs([
                        Tabs\Tab::make('Alapadatok')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\Select::make('school_id')
                                    ->label('Iskola')
                                    ->relationship('school', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Név')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('city')
                                            ->label('Város')
                                            ->maxLength(255),
                                    ]),

                                Forms\Components\Select::make('partner_id')
                                    ->label('Partner')
                                    ->relationship('partner', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->disabled(function () {
                                        $user = auth()->user();

                                        return $user && $user->hasRole('tablo') && ! $user->hasAnyRole(['super_admin', 'photo_admin']);
                                    })
                                    ->default(function () {
                                        $user = auth()->user();
                                        if ($user && $user->hasRole('tablo') && ! $user->hasAnyRole(['super_admin', 'photo_admin'])) {
                                            return $user->tablo_partner_id;
                                        }

                                        return null;
                                    })
                                    ->dehydrated(true)
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Név')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('local_id')
                                            ->label('Helyi ID')
                                            ->maxLength(255),
                                    ]),

                                Forms\Components\Select::make('tablo_gallery_id')
                                    ->label('Fotógaléria')
                                    ->relationship('gallery', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Galéria neve')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\Textarea::make('description')
                                            ->label('Leírás')
                                            ->rows(2)
                                            ->maxLength(1000),
                                    ])
                                    ->helperText('Válaszd ki a projekthez tartozó fotógalériát'),

                                Forms\Components\TextInput::make('class_name')
                                    ->label('Osztály neve')
                                    ->placeholder('pl. 12 A')
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('class_year')
                                    ->label('Évfolyam')
                                    ->placeholder('pl. 2026')
                                    ->maxLength(50),

                                Forms\Components\Select::make('status')
                                    ->label('Státusz')
                                    ->options(TabloProjectStatus::options())
                                    ->default(TabloProjectStatus::NotStarted->value)
                                    ->required(),
                            ])
                            ->columns(2),

                        Tabs\Tab::make('Belépési kód')
                            ->icon('heroicon-o-key')
                            ->schema([
                                AccessCodeSection::make(
                                    modelClass: TabloProject::class,
                                    tableName: 'tablo_projects',
                                    defaultDays: 30
                                ),
                            ]),

                        Tabs\Tab::make('Megosztás')
                            ->icon('heroicon-o-share')
                            ->schema([
                                AdminPreviewSection::make(),
                                ShareTokenSection::make(TabloProject::class),
                            ]),

                        Tabs\Tab::make('Státusz')
                            ->icon('heroicon-o-megaphone')
                            ->schema([
                                Section::make('Felhasználói státusz')
                                    ->description('Ez a státusz a frontend oldalon jelenik meg az ügyfeleknek')
                                    ->components([
                                        Forms\Components\Select::make('tablo_status_id')
                                            ->label('Projekt státusz')
                                            ->relationship('tabloStatus', 'name')
                                            ->preload()
                                            ->searchable()
                                            ->placeholder('Válassz státuszt...')
                                            ->helperText('A felhasználók által látható státusz')
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('Név')
                                                    ->required(),
                                                Forms\Components\Select::make('color')
                                                    ->label('Szín')
                                                    ->options(\App\Models\TabloStatus::getColorOptions())
                                                    ->default('gray'),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Ütemezés')
                            ->icon('heroicon-o-calendar-days')
                            ->schema([
                                Section::make()
                                    ->components([
                                        Forms\Components\DatePicker::make('photo_date')
                                            ->label('Fotózás időpontja')
                                            ->displayFormat('Y. m. d.')
                                            ->native(false)
                                            ->placeholder('Válassz dátumot...'),

                                        Forms\Components\DatePicker::make('deadline')
                                            ->label('Elkészítési határidő')
                                            ->displayFormat('Y. m. d.')
                                            ->native(false)
                                            ->placeholder('Válassz dátumot...'),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make('Azonosítók')
                            ->icon('heroicon-o-identification')
                            ->visible($isAdmin)
                            ->schema([
                                Section::make()
                                    ->components([
                                        Forms\Components\TextInput::make('fotocms_id')
                                            ->label('FotoCMS ID')
                                            ->numeric()
                                            ->unique(ignoreRecord: true)
                                            ->helperText('Azonosító a FotoCMS rendszerben'),

                                        Forms\Components\TextInput::make('external_id')
                                            ->label('Külső ID')
                                            ->maxLength(255)
                                            ->unique(ignoreRecord: true)
                                            ->helperText('Azonosító a tablokiraly.hu-n'),

                                        Forms\Components\Toggle::make('is_aware')
                                            ->label('Tudnak róla')
                                            ->helperText('Az iskola értesült-e a tablóról')
                                            ->default(false),
                                    ])
                                    ->columns(3),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->searchable()
            ->searchUsing(static::getUnaccentSearchCallback())
            ->columns([
                Tables\Columns\ImageColumn::make('sample_preview')
                    ->label('')
                    ->state(function (TabloProject $record) {
                        // Utolsó AKTÍV minta (legújabb, is_active = true)
                        $sample = $record->getMedia('samples')
                            ->filter(fn ($m) => $m->getCustomProperty('is_active', true))
                            ->sortByDesc('created_at')
                            ->first();
                        return $sample?->getUrl('thumb');
                    })
                    ->defaultImageUrl(fn () => null)
                    ->width(50)
                    ->height(50)
                    ->circular(false)
                    ->extraImgAttributes([
                        'style' => 'border-radius: 6px; object-fit: cover;',
                    ])
                    ->placeholder(
                        fn () => view('components.sample-placeholder')
                    )
                    ->action(
                        \Filament\Actions\Action::make('viewSamples')
                            ->modalContent(function (TabloProject $record) {
                                // Csak AKTÍV minták, legújabb elöl
                                $allMedia = $record->getMedia('samples')
                                    ->filter(fn ($m) => $m->getCustomProperty('is_active', true))
                                    ->sortByDesc('created_at')
                                    ->values();

                                if ($allMedia->isEmpty()) {
                                    return new \Illuminate\Support\HtmlString(
                                        '<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; color: #9ca3af;">
                                            <svg style="width: 64px; height: 64px; margin-bottom: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                                            </svg>
                                            <p style="font-size: 16px; font-weight: 500; margin: 0;">Nincs aktív minta</p>
                                            <p style="font-size: 14px; margin-top: 8px;">A Minták fülön tölthetsz fel mintákat</p>
                                        </div>'
                                    );
                                }

                                $firstMedia = $allMedia->first();
                                $mediaData = $allMedia->map(fn ($m) => [
                                    'id' => $m->id,
                                    'url' => $m->getUrl(),
                                    'name' => $m->file_name,
                                ])->values()->toArray();

                                return view('components.media-lightbox', [
                                    'imageUrl' => $firstMedia->getUrl(),
                                    'fileName' => $firstMedia->file_name,
                                    'currentIndex' => 0,
                                    'totalCount' => $allMedia->count(),
                                    'mediaData' => $mediaData,
                                ]);
                            })
                            ->modalWidth('7xl')
                            ->modalHeading(fn (TabloProject $record) => $record->school?->name . ' - Minták')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Bezárás')
                    ),

                Tables\Columns\TextColumn::make('school.name')
                    ->label('Iskola')
                    ->sortable()
                    ->weight('bold')
                    ->wrap()
                    ->formatStateUsing(function ($state, TabloProject $record) {
                        $user = auth()->user();
                        $html = e($state);

                        // Új képek jelzése az iskola név mellett (csak super admin és photo admin látja)
                        if ($record->has_new_missing_photos && $user && $user->hasAnyRole(['super_admin', 'photo_admin'])) {
                            $html .= ' <span style="display: inline-flex; align-items: center; padding: 2px 6px; background: #16a34a; color: white; border-radius: 4px; font-size: 10px; font-weight: 600; vertical-align: middle;">Új képek</span>';
                        }

                        return $html;
                    })
                    ->html()
                    ->description(function ($record) {
                        $user = auth()->user();
                        // Tablo felhasználóknak ne mutassuk a partner nevet, úgyis csak a sajátjukat látják
                        if ($user && $user->hasRole('tablo') && ! $user->hasAnyRole(['super_admin', 'photo_admin'])) {
                            return null;
                        }

                        return $record->partner?->name;
                    }),

                Tables\Columns\TextColumn::make('class_name')
                    ->label('Osztály')
                    ->sortable()
                    ->description(fn (TabloProject $record) => $record->class_year),

                Tables\Columns\TextColumn::make('partner.name')
                    ->label('Partner')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Státusz')
                    ->badge()
                    ->formatStateUsing(fn (TabloProjectStatus $state): string => $state->label())
                    ->color(fn (TabloProjectStatus $state): string => $state->color())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),


                Tables\Columns\TextColumn::make('first_contact')
                    ->label('Kapcsolattartó')
                    ->state(fn (TabloProject $record): string => $record->contacts->firstWhere('is_primary', true)?->name
                        ?? $record->contacts->first()?->name
                        ?? '-')
                    ->icon(fn (TabloProject $record) => $record->contacts->count() > 1 ? 'heroicon-m-user-group' : 'heroicon-m-user')
                    ->iconPosition('after')
                    ->color('primary')
                    ->action(
                        \Filament\Actions\Action::make('viewContacts')
                            ->modalHeading('Kapcsolattartók')
                            ->modalWidth('md')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Bezárás')
                            ->infolist(function (TabloProject $record): array {
                                $items = [];
                                foreach ($record->contacts as $index => $contact) {
                                    $items[] = Section::make($contact->name ?? 'Kapcsolattartó '.($index + 1))
                                        ->schema([
                                            \Filament\Infolists\Components\TextEntry::make('name_'.$index)
                                                ->label('Név')
                                                ->state($contact->name)
                                                ->copyable()
                                                ->hidden(! $contact->name),
                                            \Filament\Infolists\Components\TextEntry::make('email_'.$index)
                                                ->label('Email')
                                                ->state($contact->email)
                                                ->copyable()
                                                ->icon('heroicon-m-envelope')
                                                ->hidden(! $contact->email),
                                            \Filament\Infolists\Components\TextEntry::make('phone_'.$index)
                                                ->label('Telefon')
                                                ->state($contact->phone)
                                                ->copyable()
                                                ->icon('heroicon-m-phone')
                                                ->hidden(! $contact->phone),
                                            \Filament\Infolists\Components\TextEntry::make('note_'.$index)
                                                ->label('Megjegyzés')
                                                ->state($contact->note)
                                                ->hidden(! $contact->note),
                                        ])
                                        ->columns(1)
                                        ->compact();
                                }

                                return $items ?: [\Filament\Infolists\Components\TextEntry::make('empty')->state('Nincs kapcsolattartó')->label('')];
                            })
                    ),

                Tables\Columns\TextColumn::make('missing_persons_summary')
                    ->label('Hiányzók')
                    ->state(function (TabloProject $record): string {
                        $students = $record->missingPersons->where('type', 'student')->count();
                        $teachers = $record->missingPersons->where('type', 'teacher')->count();
                        if ($students === 0 && $teachers === 0) {
                            return '-';
                        }

                        return "D: {$students} / T: {$teachers}";
                    })
                    ->badge()
                    ->color(fn (TabloProject $record) => $record->missingPersons->count() > 0 ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('activity')
                    ->label('Aktivitás')
                    ->state(function (TabloProject $record): string {
                        $contactEmails = $record->contacts->pluck('email')->filter()->map(fn ($e) => strtolower($e))->toArray();

                        if (empty($contactEmails)) {
                            return '<span style="color: #9ca3af;">Nincs kontakt</span>';
                        }

                        $emails = $record->emails->filter(function ($email) use ($contactEmails) {
                            return in_array(strtolower($email->from_email ?? ''), $contactEmails)
                                || in_array(strtolower($email->to_email ?? ''), $contactEmails);
                        });

                        if ($emails->isEmpty()) {
                            return '<span style="color: #9ca3af;">Soha</span>';
                        }

                        $lastEmail = $emails->sortByDesc('email_date')->first();
                        $timeAgo = $lastEmail->email_date->diffForHumans();

                        // Ki küldött utoljára?
                        if ($lastEmail->direction === 'outbound') {
                            // Mi küldtük → rá várunk
                            return '<span style="color: #f59e0b; font-weight: 500;">⏳ Rá várok</span><br><span style="color: #9ca3af; font-size: 11px;">'.$timeAgo.'</span>';
                        } else {
                            // Ő küldte → ránk vár
                            if ($lastEmail->needs_reply && ! $lastEmail->is_replied) {
                                return '<span style="color: #ef4444; font-weight: 500;">⚠ Rám vár!</span><br><span style="color: #9ca3af; font-size: 11px;">'.$timeAgo.'</span>';
                            } else {
                                return '<span style="color: #10b981;">✓ Megválaszolva</span><br><span style="color: #9ca3af; font-size: 11px;">'.$timeAgo.'</span>';
                            }
                        }
                    })
                    ->html()
                    ->visible(function () {
                        $user = auth()->user();

                        return ! ($user && $user->hasRole('tablo') && ! $user->hasAnyRole(['super_admin', 'photo_admin']));
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(function ($query) {
                $query->with(['contacts', 'missingPersons', 'emails', 'media' => fn ($q) => $q->where('collection_name', 'samples')->orderBy('created_at', 'desc')])
                    ->selectRaw('*, (SELECT MAX(email_date) FROM project_emails WHERE project_emails.tablo_project_id = tablo_projects.id) as last_email_date');

                // Tablo szerepkörű felhasználók csak a saját partnerükhöz tartozó projekteket látják
                $user = auth()->user();
                if ($user && $user->hasRole('tablo') && ! $user->hasAnyRole(['super_admin', 'photo_admin'])) {
                    $query->where('partner_id', $user->tablo_partner_id);
                }

                return $query;
            })
            ->groups([
                Tables\Grouping\Group::make('status')
                    ->label('Státusz')
                    ->getTitleFromRecordUsing(function ($record) {
                        $color = match ($record->status->color()) {
                            'gray' => '#6b7280',
                            'warning' => '#f59e0b',
                            'info' => '#3b82f6',
                            'success' => '#10b981',
                            'danger' => '#ef4444',
                            'purple' => '#8b5cf6',
                            default => '#6b7280',
                        };

                        $count = TabloProject::where('status', $record->status)->count();

                        return new \Illuminate\Support\HtmlString(
                            '<span style="display: inline-flex; align-items: center; gap: 8px;">' .
                            '<span style="width: 12px; height: 12px; border-radius: 50%; background-color: ' . $color . ';"></span>' .
                            e($record->status->label()) .
                            '<span style="background-color: ' . $color . '; color: white; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 600;">' . $count . '</span>' .
                            '</span>'
                        );
                    })
                    ->orderQueryUsing(fn ($query, string $direction) => $query
                        ->orderByRaw('CASE status ' . collect(TabloProjectStatus::cases())
                            ->map(fn ($case) => "WHEN '{$case->value}' THEN {$case->sortOrder()}")
                            ->join(' ') . ' END ' . $direction)
                        ->orderByRaw('last_email_date DESC NULLS LAST'))
                    ->collapsible(),
                Tables\Grouping\Group::make('partner.name')
                    ->label('Partner')
                    ->collapsible(),
                Tables\Grouping\Group::make('school.name')
                    ->label('Iskola')
                    ->collapsible(),
            ])
            ->defaultGroup('status')
            ->recordClasses(function (TabloProject $record) {
                $user = auth()->user();
                if ($record->has_new_missing_photos && $user && $user->hasAnyRole(['super_admin', 'photo_admin'])) {
                    return 'bg-green-50 dark:bg-green-900/20';
                }

                return null;
            })
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Státusz')
                    ->options(TabloProjectStatus::options()),

                Tables\Filters\SelectFilter::make('school_id')
                    ->label('Iskola')
                    ->relationship('school', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('partner_id')
                    ->label('Partner')
                    ->relationship('partner', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_aware')
                    ->label('Tudnak róla')
                    ->trueLabel('Igen')
                    ->falseLabel('Nem'),
            ])
            ->actions([
                \Filament\Actions\Action::make('toggleAware')
                    ->label('')
                    ->icon(fn (TabloProject $record) => $record->is_aware ? 'heroicon-s-check-circle' : 'heroicon-o-check-circle')
                    ->color(fn (TabloProject $record) => $record->is_aware ? 'success' : 'gray')
                    ->tooltip(fn (TabloProject $record) => $record->is_aware ? 'Tudnak róla ✓' : 'Nem tudnak róla')
                    ->action(fn (TabloProject $record) => $record->update(['is_aware' => ! $record->is_aware])),
                ActionGroup::make([
                    EditAction::make(),
                    \Filament\Actions\Action::make('generateQrCode')
                        ->label('QR regisztráció')
                        ->icon('heroicon-o-qr-code')
                        ->color('success')
                        ->tooltip('QR kód generálása ügyfelek regisztrációjához')
                        ->modalHeading('QR Kód Regisztrációhoz')
                        ->modalDescription('Az ügyfelek ezzel a QR kóddal regisztrálhatnak a projekthez.')
                        ->modalWidth('md')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Bezárás')
                        ->infolist(function (TabloProject $record): array {
                            // Generálj kódot ha nincs aktív
                            $qrService = app(\App\Services\QrRegistrationService::class);
                            $activeCodes = $qrService->getActiveCodesForProject($record);

                            if ($activeCodes->isEmpty()) {
                                $qrCode = $qrService->generateCode($record);
                            } else {
                                $qrCode = $activeCodes->first();
                            }

                            $registrationUrl = $qrCode->getRegistrationUrl();

                            return [
                                \Filament\Infolists\Components\TextEntry::make('qr_preview')
                                    ->label('')
                                    ->state($registrationUrl)
                                    ->formatStateUsing(function () use ($registrationUrl) {
                                        // Base64 QR kód generálása (egyszerű SVG verzió a Google API helyett)
                                        $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($registrationUrl);

                                        return new \Illuminate\Support\HtmlString(
                                            '<div style="text-align: center; padding: 20px;">
                                                <img src="' . $qrApiUrl . '" alt="QR Code" style="width: 200px; height: 200px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" />
                                            </div>'
                                        );
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('registration_code')
                                    ->label('Regisztrációs kód')
                                    ->state($qrCode->code)
                                    ->copyable()
                                    ->badge()
                                    ->size('lg'),
                                \Filament\Infolists\Components\TextEntry::make('registration_url')
                                    ->label('Regisztrációs link')
                                    ->state($registrationUrl)
                                    ->copyable()
                                    ->url($registrationUrl, shouldOpenInNewTab: true),
                                \Filament\Infolists\Components\TextEntry::make('usage_info')
                                    ->label('Használat')
                                    ->state(function () use ($qrCode) {
                                        $usage = $qrCode->usage_count;
                                        $max = $qrCode->max_usages;

                                        return $max ? "{$usage} / {$max}" : "{$usage} (korlátlan)";
                                    }),
                            ];
                        }),
                    \Filament\Actions\Action::make('adminPreview')
                        ->label('Előnézet')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->tooltip('Megnyitás a frontend-tablo oldalon (egyszer használatos link)')
                        ->url(fn (TabloProject $record): string => route('tablo-project.admin-preview', $record))
                        ->openUrlInNewTab(),
                    \Filament\Actions\Action::make('copyShareLink')
                        ->label('Link másolása')
                        ->icon('heroicon-o-clipboard-document')
                        ->color('gray')
                        ->visible(fn (TabloProject $record): bool => $record->hasValidShareToken())
                        ->action(function (TabloProject $record) {
                            $url = $record->getShareUrl();
                            Notification::make()
                                ->title('Link kimásolva')
                                ->body($url)
                                ->success()
                                ->send();
                        })
                        ->extraAttributes(fn (TabloProject $record) => [
                            'x-data' => '{}',
                            'x-on:click' => 'navigator.clipboard.writeText(\'' . ($record->getShareUrl() ?? '') . '\')',
                        ]),
                    \Filament\Actions\Action::make('openFotocms')
                        ->label('FotoCMS')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('gray')
                        ->url(fn (TabloProject $record): string => "http://fotocms-admin.prod/tablo/project/{$record->fotocms_id}")
                        ->openUrlInNewTab()
                        ->visible(fn (TabloProject $record): bool => !empty($record->fotocms_id)),
                    \Filament\Actions\Action::make('syncFromApi')
                        ->label('Szinkron API-ból')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->visible(fn (TabloProject $record): bool => !empty($record->external_id))
                        ->requiresConfirmation()
                        ->modalHeading('Projekt szinkronizálása')
                        ->modalDescription('A projekt adatai frissítésre kerülnek a live API-ból (api.tablokiraly.hu). A névsorokat AI elemzi a nyers szövegből. Ez felülírja a jelenlegi elemzési adatokat.')
                        ->action(function (TabloProject $record): void {
                            $apiService = app(TabloApiService::class);
                            $claudeService = app(ClaudeService::class);

                            $data = $apiService->getProjectDetails((int) $record->external_id);

                            if (!$data) {
                                Notification::make()
                                    ->title('Hiba')
                                    ->body('Nem sikerült lekérni a projekt adatait az API-ból.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $summary = $apiService->extractProjectSummary($data);

                            // Elemzés frissítése
                            $analysis = \App\Models\TabloOrderAnalysis::firstOrCreate(
                                ['tablo_project_id' => $record->id],
                                ['status' => 'processing']
                            );

                            // MINDIG a nyers szövegből dolgozunk AI-val
                            $students = [];
                            $teachers = [];

                            // Diák névsor feldolgozás AI-val
                            if (!empty($data['student_description'])) {
                                $students = $apiService->parseNameListWithAI(
                                    $data['student_description'],
                                    'students',
                                    $claudeService
                                );
                            }

                            // Tanár névsor feldolgozás AI-val
                            if (!empty($data['teacher_description'])) {
                                $teachers = $apiService->parseNameListWithAI(
                                    $data['teacher_description'],
                                    'teachers',
                                    $claudeService
                                );
                            }

                            $analysis->update([
                                'status' => 'completed',
                                'analyzed_at' => now(),
                                'contact_name' => $summary['contact_name'],
                                'contact_phone' => $summary['contact_phone'],
                                'contact_email' => $summary['contact_email'],
                                'school_name' => $summary['school_name'],
                                'class_name' => $summary['class_name'],
                                'student_count' => count($students),
                                'teacher_count' => count($teachers),
                                'font_style' => $data['font_family'] ?? null,
                                'color_scheme' => $data['color'] ?? null,
                                'special_notes' => $data['description'] ?? null,
                                'analysis_data' => [
                                    'source' => 'live_api',
                                    'api_project_id' => $data['id'],
                                    'contact' => $data['contact'] ?? [],
                                    'school' => $data['school'] ?? [],
                                    'design' => [
                                        'color' => $data['color'] ?? null,
                                        'font' => $data['font_family'] ?? null,
                                        'quote' => $data['quote'] ?? null,
                                        'notes' => $data['description'] ?? null,
                                    ],
                                    // Nyers szöveg mezők (eredeti)
                                    'raw_student_description' => $data['student_description'] ?? null,
                                    'raw_teacher_description' => $data['teacher_description'] ?? null,
                                    // AI által feldolgozott nevek
                                    'students' => $students,
                                    'teachers' => $teachers,
                                    'files' => $data['files'] ?? [],
                                ],
                                'tags' => [],
                                'warnings' => [],
                            ]);

                            Notification::make()
                                ->title('Szinkronizálás kész')
                                ->body("Diákok: {$analysis->student_count}, Tanárok: {$analysis->teacher_count} (AI elemzés)")
                                ->success()
                                ->send();
                        }),
                    DeleteAction::make(),
                ])
                    ->label('')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('gray')
                    ->visible(function (): bool {
                        $user = auth()->user();
                        // Tabló partnerek NEM látják az action menüt (kattintásra úgyis edit nyílik)
                        return ! ($user && $user->hasRole('tablo') && ! $user->hasAnyRole(['super_admin', 'photo_admin']));
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('exportContacts')
                        ->label('Kontaktok exportálása')
                        ->icon('heroicon-o-phone')
                        ->color('success')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): StreamedResponse {
                            return static::exportContactsToVCard($records);
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ContactsRelationManager::class,
            RelationManagers\PersonsRelationManager::class,
            RelationManagers\NotesRelationManager::class,
            RelationManagers\SamplesRelationManager::class,
            RelationManagers\EmailsRelationManager::class,
            RelationManagers\PollsRelationManager::class,
            RelationManagers\GuestSessionsRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\TabloProjectStatsOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTabloProjects::route('/'),
            'create' => Pages\CreateTabloProject::route('/create'),
            'edit' => Pages\EditTabloProject::route('/{record}/edit'),
        ];
    }

    /**
     * Export contacts from selected TabloProjects to vCard format.
     */
    public static function exportContactsToVCard(Collection $records): StreamedResponse
    {
        $year = now()->year;
        $filename = "tablo_{$year}_contacts.vcf";

        return response()->streamDownload(function () use ($records, $year) {
            $records->load('contacts', 'school');

            foreach ($records as $project) {
                $phoneticName = static::formatPhoneticName($project);

                foreach ($project->contacts as $contact) {
                    echo static::generateVCard($contact, $phoneticName, $year);
                }
            }
        }, $filename, [
            'Content-Type' => 'text/vcard',
        ]);
    }

    /**
     * Format phonetic name: "[ 12. a ] Iskola rövidítve"
     */
    protected static function formatPhoneticName(TabloProject $project): string
    {
        $className = static::formatClassName($project->class_name);
        $schoolShort = static::shortenSchoolName($project->school?->name ?? '');

        return trim("{$className} {$schoolShort}");
    }

    /**
     * Format class name: "12 A" -> "[ 12. a ]"
     */
    protected static function formatClassName(?string $className): string
    {
        if (empty($className)) {
            return '';
        }

        // Parse class name (e.g., "12 A", "12A", "12. A")
        if (preg_match('/(\d+)\s*\.?\s*([A-Za-z])/i', $className, $matches)) {
            $number = $matches[1];
            $letter = strtolower($matches[2]);

            return "[ {$number}. {$letter} ]";
        }

        return "[ {$className} ]";
    }

    /**
     * Get school name (full, no shortening).
     */
    protected static function shortenSchoolName(?string $name): string
    {
        return trim($name ?? '');
    }

    /**
     * Generate vCard string for a contact.
     *
     * @param  mixed  $contact  Contact model
     * @param  string  $phoneticName  Phonetic name: "[ 12. a ] Iskola teljes neve"
     * @param  int  $year  Year for category
     */
    protected static function generateVCard($contact, string $phoneticName, int $year): string
    {
        $name = $contact->name ?? 'Ismeretlen';
        $phone = $contact->phone ?? '';
        $email = $contact->email ?? '';

        // Parse name into parts (simple split)
        $nameParts = preg_split('/\s+/', $name, 2);
        $lastName = $nameParts[0] ?? '';
        $firstName = $nameParts[1] ?? '';

        $vcard = "BEGIN:VCARD\r\n";
        $vcard .= "VERSION:3.0\r\n";
        $vcard .= "N:{$lastName};{$firstName};;;\r\n";
        $vcard .= "FN:{$name}\r\n";

        if ($phone) {
            $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
            $vcard .= "TEL;TYPE=CELL:{$cleanPhone}\r\n";
        }

        if ($email) {
            $vcard .= "EMAIL:{$email}\r\n";
        }

        // Phonetic last name = teljes iskola név + osztály (iPhone-on itt jelenik meg)
        if ($phoneticName) {
            $vcard .= "X-PHONETIC-LAST-NAME:{$phoneticName}\r\n";
        }

        // Category for grouping
        $vcard .= "CATEGORIES:Tablo {$year}\r\n";

        $vcard .= "END:VCARD\r\n";

        return $vcard;
    }
}

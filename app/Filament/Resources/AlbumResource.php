<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AlbumResource\Pages;
use App\Filament\Resources\AlbumResource\RelationManagers\PhotosRelationManager;
use App\Filament\Resources\WorkSessions\WorkSessionResource;
use App\Models\Album;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
use Illuminate\Support\HtmlString;

class AlbumResource extends BaseResource
{

    protected static function getPermissionKey(): string
    {
        return 'albums';
    }

    protected static ?string $model = Album::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Fotózások';

    protected static ?string $modelLabel = 'Fotózás';

    protected static ?string $pluralModelLabel = 'Fotózások';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Album')
                    ->tabs([
                        Tabs\Tab::make('Alapadatok')
                            ->icon('heroicon-o-information-circle')
                            ->visible(fn () => static::canAccessTab('basic'))
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label('Fotózás neve')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('pl. Őszi Tablófotózás 2025'),

                                Forms\Components\Select::make('school_classes')
                                    ->label('Osztályok')
                                    ->multiple()
                                    ->relationship(
                                        'schoolClasses',
                                        'label',
                                        fn ($query) => $query->orderBy('school')->orderBy('grade')->orderBy('label')
                                    )
                                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->school} - {$record->label}")
                                    ->searchable(['school', 'label'])
                                    ->preload()
                                    ->nullable()
                                    ->helperText('Opcionális: válassz osztály(ok)at. Új osztály létrehozásához lépj az Iskolai Adatok → Osztályok menüpontra.'),

                                Forms\Components\Select::make('visibility')
                                    ->label('Láthatóság')
                                    ->options([
                                        'public' => 'Publikus',
                                        'link' => 'Csak linkkel elérhető',
                                    ])
                                    ->default('link')
                                    ->live()
                                    ->required(),

                                Forms\Components\TextInput::make('public_link')
                                    ->label('Publikus link')
                                    ->default(fn ($record) => $record ? url("/album/{$record->id}") : null)
                                    ->readOnly()
                                    ->suffixIcon('heroicon-o-clipboard-document')
                                    ->extraInputAttributes([
                                        'x-on:click' => 'window.navigator.clipboard.writeText($el.value); $tooltip(\'Link vágólapra másolva!\', { timeout: 2000 })',
                                    ])
                                    ->visible(fn ($get) => $get('visibility') === 'link')
                                    ->helperText('Kattints a mezőre vagy az ikonra a link vágólapra másolásához')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Tabs\Tab::make('Statisztikák')
                            ->icon('heroicon-o-chart-bar')
                            ->visible(fn () => static::canAccessTab('statistics'))
                            ->schema([
                                Forms\Components\Placeholder::make('photos_count')
                                    ->label('Képek száma')
                                    ->content(fn ($record) => $record ? $record->photos()->count() : 0),

                                Forms\Components\Placeholder::make('assigned_count')
                                    ->label('Hozzárendelt képek')
                                    ->content(fn ($record) => $record ? $record->photos()->whereNotNull('assigned_user_id')->count() : 0),

                                Forms\Components\Placeholder::make('unassigned_count')
                                    ->label('Jelöletlen képek')
                                    ->content(fn ($record) => $record ? $record->photos()->whereNull('assigned_user_id')->count() : 0),

                                Forms\Components\Placeholder::make('created_at')
                                    ->label('Létrehozva')
                                    ->content(fn ($record) => $record ? $record->created_at->format('Y-m-d H:i') : '-'),
                            ])
                            ->columns(4)
                            ->hidden(fn ($record) => $record === null),

                        Tabs\Tab::make('Árazás és Csomagok')
                            ->icon('heroicon-o-currency-dollar')
                            ->visible(fn () => static::canAccessTab('pricing'))
                            ->schema([
                                Section::make('Csomag vagy Árlista Beállítások')
                                    ->description('Válaszd ki, hogy ez az album melyik csomagot vagy árlistát használja. Ha a munkamenet is be van állítva, az erősebb lesz.')
                                    ->schema([
                                        Forms\Components\Select::make('package_id')
                                            ->label('Csomag')
                                            ->relationship('package', 'name')
                                            ->searchable()
                                            ->nullable()
                                            ->helperText('Fix csomag ezen albumhoz')
                                            ->live()
                                            ->preload(),

                                        Forms\Components\Select::make('price_list_id')
                                            ->label('Árlista')
                                            ->relationship('priceList', 'name')
                                            ->searchable()
                                            ->nullable()
                                            ->helperText('Egyedi árlista ezen albumhoz')
                                            ->visible(fn ($get) => ! $get('package_id'))
                                            ->preload(),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Fotózás')
                    ->description(function (Album $record) {
                        $descriptions = [];

                        // Child albumok száma (hasonlóan mint a work sessionöknél)
                        $childCount = $record->child_albums_count ?? 0;
                        if ($childCount > 0) {
                            $descriptions[] = "{$childCount} al-album";
                        }

                        // Munkamenetek badge-ekkel (CSAK SZÜLŐ munkamenetek!)
                        if ($record->workSessions->isNotEmpty()) {
                            // Szűrjük ki a szülő munkameneteket (amelyeknek nincs parent_work_session_id-ja)
                            $parentSessions = $record->workSessions->filter(function ($session) {
                                return $session->parent_work_session_id === null;
                            });

                            if ($parentSessions->isNotEmpty()) {
                                $badges = $parentSessions->map(function ($session) {
                                    $url = WorkSessionResource::getUrl('edit', ['record' => $session->id]);
                                    $displayName = \Illuminate\Support\Str::limit($session->name, 25, '...');
                                    return '<a href="'.$url.'" style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.5rem; font-size: 0.75rem; font-weight: 500; border-radius: 0.375rem; background-color: #fef9c3; color: #78350f; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor=\'#fef3c7\'" onmouseout="this.style.backgroundColor=\'#fef9c3\'" title="'.$session->name.'">
                                        <svg style="width: 0.75rem; height: 0.75rem;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                        </svg>
                                        '.$displayName.'
                                    </a>';
                                })->implode(' ');
                                $descriptions[] = $badges;
                            }
                        }

                        return $descriptions ? new HtmlString(implode('<br>', $descriptions)) : null;
                    })
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('photos_count')
                    ->label('Képek összesen')
                    ->counts('photos')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('photo_assignment')
                    ->label('Hozzárendelés')
                    ->visible(false)
                    ->formatStateUsing(function ($record) {
                        $assigned = $record->photos()->whereNotNull('assigned_user_id')->count();
                        $unassigned = $record->photos()->whereNull('assigned_user_id')->count();

                        return "{$assigned} / {$unassigned}";
                    })
                    ->description('Hozzárendelt / Jelöletlen')
                    ->badge()
                    ->color('info')
                    ->alignCenter()
                    ->sortable(false),

                Tables\Columns\TextColumn::make('visibility')
                    ->label('Láthatóság')
                    ->visible(false)
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'public' => 'success',
                        'link' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state === 'public' ? 'Publikus' : 'Link'),

                Tables\Columns\TextColumn::make('processing_status')
                    ->label('Feldolgozás folyamata')
                    ->visible(false)
                    ->formatStateUsing(function ($record) {
                        $statuses = [];

                        // ZIP processing status
                        $zipStatus = match ($record->zip_processing_status) {
                            'pending' => 'Várakozás',
                            'processing' => $record->zip_total_images
                                ? "{$record->zip_processed_images}/{$record->zip_total_images} kép"
                                : 'Folyamatban',
                            'completed' => 'Kész',
                            'failed' => 'Hiba',
                            default => 'Nincs',
                        };
                        $statuses[] = "ZIP: {$zipStatus}";

                        // Face recognition status
                        $faceStatus = match ($record->face_grouping_status) {
                            'pending' => 'Várakozás',
                            'processing' => $record->face_total_photos
                                ? "{$record->face_processed_photos}/{$record->face_total_photos} fotó"
                                : 'Folyamatban',
                            'completed' => 'Kész',
                            'failed' => 'Hiba',
                            default => 'Nincs',
                        };
                        $statuses[] = "Arcfelismerés: {$faceStatus}";

                        return new HtmlString(
                            '<div class="flex flex-col gap-1">'.
                            implode('', array_map(function ($status) {
                                $color = 'gray';
                                if (str_contains($status, 'Várakozás')) {
                                    $color = 'warning';
                                }
                                if (str_contains($status, 'Folyamatban') || str_contains($status, 'kép') || str_contains($status, 'fotó')) {
                                    $color = 'info';
                                }
                                if (str_contains($status, 'Kész')) {
                                    $color = 'success';
                                }
                                if (str_contains($status, 'Hiba')) {
                                    $color = 'danger';
                                }

                                return '<span class="fi-badge fi-badge-'.$color.' text-xs">'.$status.'</span>';
                            }, $statuses)).
                            '</div>'
                        );
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('schoolClasses')
                    ->label('Osztályok')
                    ->relationship('schoolClasses', 'label')
                    ->multiple(),

                Tables\Filters\SelectFilter::make('visibility')
                    ->label('Láthatóság')
                    ->options([
                        'public' => 'Publikus',
                        'link' => 'Csak linkkel',
                    ]),
            ])
            ->headerActions([
                Action::make('watermark_settings')
                    ->label('Vízjel Beállítások')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('gray')
                    ->fillForm(function () {
                        return [
                            'watermark_enabled' => \App\Models\Setting::get('watermark_enabled', true),
                            'watermark_text' => \App\Models\Setting::get('watermark_text', 'Tablokirály'),
                        ];
                    })
                    ->form([
                        Forms\Components\Toggle::make('watermark_enabled')
                            ->label('Vízjel bekapcsolása (globális)')
                            ->helperText('Ha bekapcsolva, minden feltöltött preview képen megjelenik a vízjel.')
                            ->live()
                            ->default(true)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('watermark_text')
                            ->label('Vízjel szöveg')
                            ->placeholder('pl. Tablokirály')
                            ->helperText('Ez a szöveg fog megjelenni a képek körül, halványan.')
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('watermark_enabled'))
                            ->required(fn ($get) => $get('watermark_enabled'))
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('watermark_info')
                            ->label('Vízjel működése')
                            ->content(new HtmlString('
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    <ul class="list-disc list-inside space-y-1">
                                        <li>A kép közepét nem zavarja</li>
                                        <li>Körbe halványan jelenik meg</li>
                                        <li>Csak a preview képeken jelenik meg (1200x1200px)</li>
                                        <li>A thumbnail-eken NEM jelenik meg (300x300px)</li>
                                        <li>Ez a beállítás MINDEN albumra vonatkozik</li>
                                    </ul>
                                </div>
                            '))
                            ->visible(fn ($get) => $get('watermark_enabled'))
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data) {
                        \App\Models\Setting::set('watermark_enabled', $data['watermark_enabled']);
                        \App\Models\Setting::set('watermark_text', $data['watermark_text'] ?? 'Tablokirály');

                        \Filament\Notifications\Notification::make()
                            ->title('Beállítások mentve')
                            ->body('A globális vízjel beállítások sikeresen frissítve.')
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Globális Vízjel Beállítások')
                    ->modalWidth('2xl')
                    ->modalSubmitActionLabel('Mentés'),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->label('Szerkesztés'),

                    DeleteAction::make()
                        ->label('Törlés'),

                    Action::make('groupAllFaces')
                        ->label('Arcok csoportosítása (összes)')
                        ->icon('heroicon-o-user-group')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Összes fotó újracsoportosítása')
                        ->modalDescription('Minden fotó arcfelismerése újra lefut. Korábbi csoportosítások törlődnek.')
                        ->action(function ($record) {
                            $record->update([
                                'face_grouping_status' => 'pending',
                                'face_total_photos' => $record->photos()->count(),
                                'face_processed_photos' => 0,
                            ]);

                            // Töröljük a korábbi csoportokat
                            $record->faceGroups()->delete();

                            \App\Jobs\ProcessFaceGrouping::dispatch($record->id, 'all');

                            \Filament\Notifications\Notification::make()
                                ->title('Arcfelismerés elindítva')
                                ->body('A csoportosítás háttérben fut.')
                                ->success()
                                ->send();
                        })
                        ->visible(fn ($record) => $record->photos()->count() > 0),
                    Action::make('checkMediaIntegrity')
                        ->label('Képfájlok ellenőrzése')
                        ->icon('heroicon-o-document-magnifying-glass')
                        ->color('info')
                        ->modalHeading('Média Integritás Ellenőrzés')
                        ->modalDescription(fn ($record) => "Album: {$record->title}")
                        ->modalContent(function ($record) {
                            $photos = $record->photos()->with('media')->get();

                            $missing = [
                                'no_media' => [],
                                'no_preview' => [],
                                'no_thumb' => [],
                            ];

                            foreach ($photos as $photo) {
                                $media = $photo->getFirstMedia('photo');

                                if (! $media) {
                                    $missing['no_media'][] = $photo;

                                    continue;
                                }

                                if (! $media->hasGeneratedConversion('preview')) {
                                    $missing['no_preview'][] = $photo;
                                }

                                if (! $media->hasGeneratedConversion('thumb')) {
                                    $missing['no_thumb'][] = $photo;
                                }
                            }

                            $totalPhotos = $photos->count();
                            $missingMediaCount = count($missing['no_media']);
                            $missingPreviewCount = count($missing['no_preview']);
                            $missingThumbCount = count($missing['no_thumb']);

                            $allOk = $missingMediaCount === 0 && $missingPreviewCount === 0 && $missingThumbCount === 0;

                            $statusColor = $allOk ? 'success' : 'warning';
                            $statusIcon = $allOk ? '✅' : '⚠️';
                            $statusText = $allOk ? 'Minden rendben!' : 'Hiányzó fájlok találhatók';

                            $content = '<div class="space-y-4">';

                            // Status banner
                            $content .= '<div class="fi-badge fi-badge-'.$statusColor.' text-base p-3 rounded-lg">';
                            $content .= $statusIcon.' '.$statusText;
                            $content .= '</div>';

                            // Statistics
                            $content .= '<div class="space-y-2">';
                            $content .= '<h3 class="text-lg font-semibold">📊 Statisztika:</h3>';
                            $content .= '<ul class="list-disc list-inside space-y-1 text-sm">';
                            $content .= '<li><strong>Összes kép:</strong> '.$totalPhotos.' db</li>';
                            $content .= '<li><strong>Hiányzó eredeti fájl:</strong> <span class="fi-badge fi-badge-'.($missingMediaCount > 0 ? 'danger' : 'success').'">'.$missingMediaCount.' db</span></li>';
                            $content .= '<li><strong>Hiányzó preview:</strong> <span class="fi-badge fi-badge-'.($missingPreviewCount > 0 ? 'warning' : 'success').'">'.$missingPreviewCount.' db</span></li>';
                            $content .= '<li><strong>Hiányzó thumbnail:</strong> <span class="fi-badge fi-badge-'.($missingThumbCount > 0 ? 'warning' : 'success').'">'.$missingThumbCount.' db</span></li>';
                            $content .= '</ul>';
                            $content .= '</div>';

                            // Details
                            if (! $allOk) {
                                $content .= '<div class="space-y-3">';
                                $content .= '<h3 class="text-lg font-semibold">📋 Részletek:</h3>';

                                if ($missingMediaCount > 0) {
                                    $filenames = array_map(fn ($photo) => $photo->original_filename ?? "photo_{$photo->id}", $missing['no_media']);
                                    $filenamesList = implode(', ', $filenames);
                                    $copyId = 'copy-media-'.uniqid();

                                    $content .= '<div class="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">';
                                    $content .= '<div class="flex items-center justify-between mb-2">';
                                    $content .= '<p class="font-semibold text-red-700 dark:text-red-400">📄 Hiányzó eredeti fájlok:</p>';
                                    $content .= '<button type="button" x-data x-on:click="window.navigator.clipboard.writeText(\''.str_replace("'", "\\'", $filenamesList).'\'); $tooltip(\'Vágólapra másolva!\', { timeout: 2000 })" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300" title="Fájlnevek másolása">';
                                    $content .= '<svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" /></svg>';
                                    $content .= '</button>';
                                    $content .= '</div>';
                                    $content .= '<div class="text-sm text-red-600 dark:text-red-300 max-h-[200px] overflow-y-auto p-2 bg-white/50 dark:bg-black/20 rounded border border-red-200 dark:border-red-800">';
                                    $content .= htmlspecialchars($filenamesList);
                                    $content .= '</div>';
                                    $content .= '</div>';
                                }

                                if ($missingPreviewCount > 0) {
                                    $filenames = array_map(fn ($photo) => $photo->original_filename ?? "photo_{$photo->id}", $missing['no_preview']);
                                    $filenamesList = implode(', ', $filenames);

                                    $content .= '<div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">';
                                    $content .= '<div class="flex items-center justify-between mb-2">';
                                    $content .= '<p class="font-semibold text-yellow-700 dark:text-yellow-400">🖼️ Hiányzó preview képek:</p>';
                                    $content .= '<button type="button" x-data x-on:click="window.navigator.clipboard.writeText(\''.str_replace("'", "\\'", $filenamesList).'\'); $tooltip(\'Vágólapra másolva!\', { timeout: 2000 })" class="text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300" title="Fájlnevek másolása">';
                                    $content .= '<svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" /></svg>';
                                    $content .= '</button>';
                                    $content .= '</div>';
                                    $content .= '<div class="text-sm text-yellow-600 dark:text-yellow-300 max-h-[200px] overflow-y-auto p-2 bg-white/50 dark:bg-black/20 rounded border border-yellow-200 dark:border-yellow-800">';
                                    $content .= htmlspecialchars($filenamesList);
                                    $content .= '</div>';
                                    $content .= '</div>';
                                }

                                if ($missingThumbCount > 0) {
                                    $filenames = array_map(fn ($photo) => $photo->original_filename ?? "photo_{$photo->id}", $missing['no_thumb']);
                                    $filenamesList = implode(', ', $filenames);

                                    $content .= '<div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">';
                                    $content .= '<div class="flex items-center justify-between mb-2">';
                                    $content .= '<p class="font-semibold text-yellow-700 dark:text-yellow-400">🔍 Hiányzó thumbnail képek:</p>';
                                    $content .= '<button type="button" x-data x-on:click="window.navigator.clipboard.writeText(\''.str_replace("'", "\\'", $filenamesList).'\'); $tooltip(\'Vágólapra másolva!\', { timeout: 2000 })" class="text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300" title="Fájlnevek másolása">';
                                    $content .= '<svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" /></svg>';
                                    $content .= '</button>';
                                    $content .= '</div>';
                                    $content .= '<div class="text-sm text-yellow-600 dark:text-yellow-300 max-h-[200px] overflow-y-auto p-2 bg-white/50 dark:bg-black/20 rounded border border-yellow-200 dark:border-yellow-800">';
                                    $content .= htmlspecialchars($filenamesList);
                                    $content .= '</div>';
                                    $content .= '</div>';
                                }

                                $content .= '</div>';
                            }

                            $content .= '</div>';

                            return new HtmlString($content);
                        })
                        ->modalFooterActions(function ($record) {
                            $actions = [];

                            // Check if there are any missing items
                            $photos = $record->photos()->with('media')->get();
                            $hasMissing = false;

                            foreach ($photos as $photo) {
                                $media = $photo->getFirstMedia('photo');

                                if (! $media || ! $media->hasGeneratedConversion('preview') || ! $media->hasGeneratedConversion('thumb')) {
                                    $hasMissing = true;

                                    break;
                                }
                            }

                            // Excel Export button (only if there are missing items)
                            if ($hasMissing) {
                                $actions[] = Action::make('exportExcel')
                                    ->label('Excel Export')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->color('success')
                                    ->action(function () use ($record) {
                                        // Collect missing photos
                                        $photos = $record->photos()->with('media')->get();

                                        $missing = [
                                            'no_media' => [],
                                            'no_preview' => [],
                                            'no_thumb' => [],
                                        ];

                                        foreach ($photos as $photo) {
                                            $media = $photo->getFirstMedia('photo');

                                            if (! $media) {
                                                $missing['no_media'][] = $photo;

                                                continue;
                                            }

                                            if (! $media->hasGeneratedConversion('preview')) {
                                                $missing['no_preview'][] = $photo;
                                            }

                                            if (! $media->hasGeneratedConversion('thumb')) {
                                                $missing['no_thumb'][] = $photo;
                                            }
                                        }

                                        // Generate CSV
                                        $fileName = 'album-'.$record->id.'-hianyzok-'.now()->format('Y-m-d-His').'.csv';

                                        return response()->streamDownload(function () use ($missing) {
                                            $stream = fopen('php://output', 'w');

                                            // UTF-8 BOM for Excel compatibility
                                            fprintf($stream, chr(0xEF).chr(0xBB).chr(0xBF));

                                            // CSV header
                                            fputcsv($stream, ['Photo ID', 'Eredeti fájlnév', 'Hiányzó elem', 'Képméret']);

                                            // Missing media files
                                            foreach ($missing['no_media'] as $photo) {
                                                fputcsv($stream, [
                                                    $photo->id,
                                                    $photo->original_filename ?? "photo_{$photo->id}",
                                                    'Eredeti fájl',
                                                    $photo->width && $photo->height ? "{$photo->width}x{$photo->height}" : 'N/A',
                                                ]);
                                            }

                                            // Missing preview conversions
                                            foreach ($missing['no_preview'] as $photo) {
                                                fputcsv($stream, [
                                                    $photo->id,
                                                    $photo->original_filename ?? "photo_{$photo->id}",
                                                    'Preview',
                                                    $photo->width && $photo->height ? "{$photo->width}x{$photo->height}" : 'N/A',
                                                ]);
                                            }

                                            // Missing thumbnail conversions
                                            foreach ($missing['no_thumb'] as $photo) {
                                                fputcsv($stream, [
                                                    $photo->id,
                                                    $photo->original_filename ?? "photo_{$photo->id}",
                                                    'Thumbnail',
                                                    $photo->width && $photo->height ? "{$photo->width}x{$photo->height}" : 'N/A',
                                                ]);
                                            }

                                            fclose($stream);
                                        }, $fileName, [
                                            'Content-Type' => 'text/csv; charset=UTF-8',
                                        ]);
                                    });
                            }

                            // Close button
                            $actions[] = Action::make('close')
                                ->label('Bezárás')
                                ->color('gray')
                                ->action(fn () => null);

                            return $actions;
                        })
                        ->modalWidth('2xl')
                        ->visible(fn ($record) => $record->photos()->count() > 0),

                    Action::make('viewWorkSessions')
                        ->label('Munkamenetek megtekintése')
                        ->icon('heroicon-o-rectangle-stack')
                        ->color('info')
                        ->url(fn ($record) => WorkSessionResource::getUrl('index', [
                            'tableFilters' => [
                                'id' => [
                                    'values' => $record->workSessions()->pluck('work_sessions.id')->toArray(),
                                ],
                            ],
                        ]))
                        ->visible(fn ($record) => $record->workSessions()->count() > 0),
                ])
                    ->label('Műveletek')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(function ($query) {
                // Csak szülő albumok (almunkamenet-albumok kiszűrése)
                $query->whereNull('parent_album_id')
                    ->withCount('childAlbums'); // Eager loading a child albums számához

                $newAlbumId = session('new_album_id');

                // Eager load workSessions to avoid N+1 query problem
                $query->with('workSessions');

                if ($newAlbumId) {
                    return $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$newAlbumId])
                        ->orderBy('created_at', 'desc');
                }

                return $query->orderBy('created_at', 'desc');
            })
            ->recordClasses(function (Album $record) {
                $createdAt = $record->created_at;
                $tenSecondsAgo = now()->subSeconds(10);

                return $createdAt && $createdAt->isAfter($tenSecondsAgo)
                    ? 'fi-ta-row-new'
                    : null;
            })
            ->defaultSort('created_at', 'desc')
            ->poll('5s');
    }

    public static function getRelations(): array
    {
        return [
            PhotosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAlbums::route('/'),
            'create' => Pages\CreateAlbum::route('/create'),
            'edit' => Pages\EditAlbum::route('/{record}/edit'),
        ];
    }
}

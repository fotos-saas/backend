<?php

namespace App\Filament\Resources\WorkSessions\Tables;

use App\Models\Coupon;
use App\Models\SchoolClass;
use App\Models\WorkSession;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class WorkSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Név')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(function ($record) {
                        $childCount = $record->child_sessions_count ?? 0;
                        return $childCount > 0 ? "{$childCount} almunkamenet" : null;
                    }),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->visible(fn ($record) => $record && !$record->is_tablo_mode && $record->user_login_enabled)
                    ->badge()
                    ->color('success')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('digit_code')
                    ->label('Digit Kód')
                    ->badge()
                    ->color('warning')
                    ->visible(fn ($record) => $record && $record->digit_code_enabled)
                    ->formatStateUsing(fn ($state) => $state ? "🔑 {$state}" : '-')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('share_enabled')
                    ->label('Share Link')
                    ->boolean()
                    ->trueIcon('heroicon-o-link')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('info')
                    ->falseColor('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('albums_count')
                    ->label('Albumok')
                    ->counts('albums')
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->visible(fn ($record) => $record && !$record->is_tablo_mode),

                Tables\Columns\TextColumn::make('status')
                    ->label('Státusz')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'archived' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'active' => 'Aktív',
                        'inactive' => 'Inaktív',
                        'archived' => 'Archivált',
                        default => ucfirst((string) $state),
                    }),

                Tables\Columns\TextColumn::make('coupon_policy')
                    ->label('Kupon szabály')
                    ->formatStateUsing(function ($state, WorkSession $record) {
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

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Státusz')
                    ->options([
                        'active' => 'Aktív',
                        'inactive' => 'Inaktív',
                        'archived' => 'Archivált',
                    ]),

                Tables\Filters\TernaryFilter::make('user_login_enabled')
                    ->label('User belépés')
                    ->placeholder('Mindegyik')
                    ->trueLabel('Engedélyezett')
                    ->falseLabel('Tiltott'),

                Tables\Filters\TernaryFilter::make('digit_code_enabled')
                    ->label('Digit kód')
                    ->placeholder('Mindegyik')
                    ->trueLabel('Engedélyezett')
                    ->falseLabel('Tiltott'),

                Tables\Filters\TernaryFilter::make('share_enabled')
                    ->label('Share link')
                    ->placeholder('Mindegyik')
                    ->trueLabel('Engedélyezett')
                    ->falseLabel('Tiltott'),

                TrashedFilter::make(),
            ])
            ->recordActions([
                // Letöltés manager - külön inline gomb
                Action::make('download_manager')
                    ->label('Letöltés')
                    ->button()
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->visible(fn (WorkSession $record) =>
                        $record->is_tablo_mode &&
                        $record->childSessions()->count() > 0
                    )
                    ->modalHeading('Letöltés manager')
                    ->modalDescription('Válaszd ki az export típust, felhasználókat és képtípust.')
                    ->modalSubmitActionLabel('Előkészítés indítása')
                    ->form(function (WorkSession $record) {
                        // Get users with photos
                        $usersWithPhotos = \App\Models\TabloUserProgress::where(function ($query) use ($record) {
                                $query->where('work_session_id', $record->id)
                                    ->orWhereHas('childWorkSession', function ($q) use ($record) {
                                        $q->where('parent_work_session_id', $record->id);
                                    });
                            })
                            ->with('user.tabloRegistration.schoolClass')
                            ->get()
                            ->filter(function ($progress) {
                                $stepsData = $progress->steps_data ?? [];
                                $claimedPhotoIds = $stepsData['claimed_photo_ids'] ?? [];

                                return ! empty($claimedPhotoIds)
                                    && $progress->user
                                    && ! $progress->user->isGuest();
                            })
                            ->pluck('user')
                            ->unique('id');

                        $userOptions = $usersWithPhotos->mapWithKeys(fn ($user) => [$user->id => $user->name])->toArray();
                        $totalUsers = $usersWithPhotos->count();

                        // Get unique school classes from this work session's users
                        $classIds = $usersWithPhotos
                            ->map(fn ($user) => $user->tabloRegistration?->school_class_id)
                            ->filter()
                            ->unique()
                            ->toArray();

                        $classOptions = SchoolClass::whereIn('id', $classIds)
                            ->get()
                            ->mapWithKeys(fn ($class) => [$class->id => $class->full_label])
                            ->toArray();

                        return [
                            Forms\Components\Radio::make('export_type')
                                ->label('Export típus')
                                ->options([
                                    'zip' => 'ZIP fájl',
                                    'excel' => 'Excel táblázat',
                                ])
                                ->descriptions([
                                    'zip' => 'Képek letöltése ZIP archívumban',
                                    'excel' => 'Felhasználók és képszámok Excel táblázatban (3 munkalap: Saját képek, Returálandó, Tablókép)',
                                ])
                                ->default('excel')
                                ->required()
                                ->live()
                                ->inline(),

                            Forms\Components\Toggle::make('select_all_users')
                                ->label('Mindenki kiválasztva')
                                ->default(true)
                                ->live()
                                ->helperText('Összes felhasználó kiválasztása egyszerre'),

                            Forms\Components\Select::make('class_ids')
                                ->label('Osztály szűrés')
                                ->multiple()
                                ->searchable()
                                ->options($classOptions)
                                ->placeholder('Válassz osztályokat...')
                                ->helperText('Ha üresen hagyod, minden osztály szerepel')
                                ->visible(fn (Get $get) => $get('select_all_users') === true),

                            Forms\Components\Placeholder::make('all_users_info')
                                ->label('Kiválasztott felhasználók')
                                ->content(fn () => "Minden felhasználó kiválasztva ({$totalUsers} fő)")
                                ->visible(fn (Get $get) => $get('select_all_users') === true),

                            Forms\Components\Select::make('user_ids')
                                ->label('Felhasználók')
                                ->multiple()
                                ->searchable()
                                ->options($userOptions)
                                ->placeholder('Kezdj el gépelni...')
                                ->helperText('Válaszd ki a letölteni kívánt felhasználókat')
                                ->visible(fn (Get $get) => $get('select_all_users') === false),

                            Forms\Components\Radio::make('photo_type')
                                ->label('Képtípus')
                                ->options([
                                    'claimed' => 'Kiválasztott képek',
                                    'retus' => 'Retusálandó képek',
                                    'tablo' => 'Tabló kép',
                                ])
                                ->descriptions([
                                    'claimed' => 'A felhasználó által kiválasztott összes kép',
                                    'retus' => 'Csak a retusálásra kijelölt képek',
                                    'tablo' => 'Csak a tabló képnek választott kép',
                                ])
                                ->default('claimed')
                                ->required()
                                ->visible(fn (Get $get) => $get('export_type') === 'zip')
                                ->inline(),

                            Forms\Components\Radio::make('filename_mode')
                                ->label('Fájlnév formátum')
                                ->options([
                                    'original' => 'Eredeti fájlnév',
                                    'user_name' => 'Felhasználó neve',
                                    'original_exif' => 'Eredeti + EXIF metadata',
                                ])
                                ->descriptions([
                                    'original' => 'Megtartja az eredeti fájlnevet (pl. IMG_1234.jpg)',
                                    'user_name' => 'Átnevezi a felhasználó nevére (pl. Kovács János.jpg)',
                                    'original_exif' => 'Eredeti név + EXIF Title mezőbe írja a felhasználó nevét',
                                ])
                                ->default('original')
                                ->required()
                                ->visible(fn (Get $get) => $get('export_type') === 'zip')
                                ->inline(),
                        ];
                    })
                    ->action(function (array $data, WorkSession $record) {
                        // Determine user IDs
                        if ($data['select_all_users'] === true) {
                            // All users mode
                            $classIds = $data['class_ids'] ?? [];

                            $userIds = \App\Models\TabloUserProgress::where(function ($query) use ($record) {
                                    $query->where('work_session_id', $record->id)
                                        ->orWhereHas('childWorkSession', function ($q) use ($record) {
                                            $q->where('parent_work_session_id', $record->id);
                                        });
                                })
                                ->with('user.tabloRegistration.schoolClass')
                                ->get()
                                ->filter(function ($progress) use ($classIds) {
                                    $stepsData = $progress->steps_data ?? [];
                                    $claimedPhotoIds = $stepsData['claimed_photo_ids'] ?? [];

                                    // Basic filtering
                                    if (empty($claimedPhotoIds) || !$progress->user || $progress->user->isGuest()) {
                                        return false;
                                    }

                                    // Class filtering (if class_ids specified)
                                    if (!empty($classIds)) {
                                        $userClassId = $progress->user->tabloRegistration?->school_class_id;
                                        if (!$userClassId || !in_array($userClassId, $classIds)) {
                                            return false;
                                        }
                                    }

                                    return true;
                                })
                                ->pluck('user.id')
                                ->unique()
                                ->toArray();
                        } else {
                            // Selected users mode - filter out guest users
                            $selectedUserIds = $data['user_ids'] ?? [];
                            $userIds = \App\Models\User::whereIn('id', $selectedUserIds)
                                ->get()
                                ->filter(fn ($user) => ! $user->isGuest())
                                ->pluck('id')
                                ->toArray();
                        }

                        if (empty($userIds)) {
                            Notification::make()
                                ->title('Nincs kiválasztás')
                                ->warning()
                                ->body('Legalább egy felhasználót ki kell választani!')
                                ->send();

                            return;
                        }

                        $exportType = $data['export_type'] ?? 'zip';

                        // Excel export (azonnali letöltés)
                        if ($exportType === 'excel') {
                            try {
                                $excelService = app(\App\Services\ExcelExportService::class);
                                $tempFile = $excelService->generateManagerExcel(
                                    $record,
                                    $userIds
                                );

                                $fileName = "{$record->id} - {$record->name}.xlsx";

                                Notification::make()
                                    ->title('Excel generálás sikeres')
                                    ->success()
                                    ->body('Az Excel fájl letöltése hamarosan elindul...')
                                    ->send();

                                // Letöltés indítása (temp fájl törlésével)
                                return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);

                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Hiba történt')
                                    ->danger()
                                    ->body('Nem sikerült az Excel fájl generálása: ' . $e->getMessage())
                                    ->send();

                                return;
                            }
                        }

                        // ZIP export (háttérben fut, emailben értesítés)
                        try {
                            // Generate unique download ID for progress tracking
                            $downloadId = \Illuminate\Support\Str::uuid()->toString();

                            // Dispatch job to queue
                            \App\Jobs\GenerateManagerZipJob::dispatch(
                                $record,
                                $userIds,
                                $data['photo_type'],
                                $data['filename_mode'] ?? 'original',
                                auth()->id(), // Current admin user
                                $downloadId
                            );

                            Notification::make()
                                ->title('ZIP előkészítése megkezdődött')
                                ->success()
                                ->body('Értesítést kapsz emailben, amikor a ZIP fájl elkészült és letölthető.')
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Hiba történt')
                                ->danger()
                                ->body('Nem sikerült elindítani a ZIP előkészítését: ' . $e->getMessage())
                                ->send();
                        }
                    }),

                // Többi művelet - ActionGroup dropdown
                ActionGroup::make([
                    EditAction::make()
                        ->label('Szerkesztés'),
                    Action::make('download_albums_zip')
                        ->label('Albumok letöltése ZIP-ben')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->color('info')
                        ->visible(fn (WorkSession $record) => $record->albums()->count() > 0)
                        ->modalHeading('Albumok kiválasztása letöltéshez')
                        ->modalDescription('Válaszd ki, mely albumokat szeretnéd letölteni ZIP fájlként.')
                        ->modalSubmitActionLabel('Letöltés')
                        ->form(fn (WorkSession $record) => [
                            Forms\Components\Select::make('album_ids')
                                ->label('Albumok')
                                ->multiple()
                                ->options($record->albums->mapWithKeys(function ($album) {
                                    return [$album->id => $album->title ?? $album->name ?? "Album #{$album->id}"];
                                }))
                                ->placeholder('Válassz egy vagy több albumot...')
                                ->required()
                                ->helperText('Kiválaszthatod az összeset vagy csak néhányat')
                                ->suffixActions([
                                    Action::make('selectAll')
                                        ->label('Mind')
                                        ->icon('heroicon-o-check-circle')
                                        ->action(function ($set, $record) {
                                            $set('album_ids', $record->albums->pluck('id')->toArray());
                                        }),
                                    Action::make('deselectAll')
                                        ->label('Törlés')
                                        ->icon('heroicon-o-x-circle')
                                        ->action(fn ($set) => $set('album_ids', [])),
                                ]),
                        ])
                        ->action(function (array $data, WorkSession $record) {
                            $albumIds = $data['album_ids'] ?? [];

                            // Build download URL with album IDs as query parameters
                            $url = route('api.work-sessions.download-albums-zip', [
                                'workSession' => $record->id,
                            ]);

                            // Add album IDs as query string
                            if (! empty($albumIds)) {
                                $url .= '?album_ids='.implode(',', $albumIds);
                            }

                            // Send notification
                            Notification::make()
                                ->title('Letöltés elkezdődött')
                                ->success()
                                ->body(count($albumIds).' album letöltése folyamatban...')
                                ->send();

                            // Trigger download via JavaScript
                            return redirect($url);
                        }),
                    DeleteAction::make()
                        ->label('Törlés'),
                ])
                    ->label('Műveletek')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('gray')
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->recordClasses(function ($record) {
                $createdAt = $record->created_at;
                $tenSecondsAgo = now()->subSeconds(10);

                if ($createdAt && $createdAt->isAfter($tenSecondsAgo)) {
                    return 'fi-ta-row-new';
                }

                return null;
            })
            ->modifyQueryUsing(function ($query) {
                // Csak szülő munkamenetek (almunkamenetek kiszűrése)
                $query->whereNull('parent_work_session_id')
                    ->withCount('childSessions'); // Eager loading a child sessions számához

                $newSessionId = session('new_work_session_id');

                if ($newSessionId) {
                    return $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$newSessionId])
                        ->orderBy('created_at', 'desc');
                }

                return $query->orderBy('created_at', 'desc');
            })
            ->defaultSort('created_at', 'desc');
    }
}

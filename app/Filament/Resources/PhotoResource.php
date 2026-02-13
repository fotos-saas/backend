<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PhotoResource\Pages;
use App\Models\Photo;
use App\Models\Setting;
use App\Services\WatermarkService;
use App\Support\Filament\Lightbox\LightboxPreviewableAction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PhotoResource extends BaseResource
{

    protected static ?string $model = Photo::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Képek';

    protected static ?string $modelLabel = 'Kép';

    protected static ?string $pluralModelLabel = 'Képek';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->tabs([
                        Tabs\Tab::make('Alapadatok')
                            ->schema([
                                Section::make('Kép')
                                    ->schema([
                                        Forms\Components\FileUpload::make('photo')
                                            ->label('Kép cseréje')
                                            ->image()
                                            ->disk('public')
                                            ->preserveFilenames()
                                            ->maxSize(4194304)
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg'])
                                            ->helperText('Új kép feltöltése - a jelenlegi kép előzményekbe kerül'),

                                        Forms\Components\Textarea::make('version_reason')
                                            ->label('Csere indoklása (opcionális)')
                                            ->helperText('Miért cseréled le a képet? (pl. rossz kép, régi kép, hibás)')
                                            ->rows(2)
                                            ->dehydrated(false),
                                    ]),

                                Section::make('Albumhoz rendelés')
                                    ->schema([
                                        Forms\Components\Select::make('album_id')
                                            ->label('Album')
                                            ->relationship('album', 'title')
                                            ->searchable()
                                            ->preload()
                                            ->required(),

                                        Forms\Components\Select::make('assigned_user_id')
                                            ->label('Hozzárendelve')
                                            ->relationship('assignedUser', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),
                                    ])
                                    ->columns(2),

                                Section::make('Metaadatok')
                                    ->schema([
                                        Forms\Components\TextInput::make('path')
                                            ->label('Fájl elérési út')
                                            ->disabled()
                                            ->dehydrated(false),

                                        Forms\Components\TextInput::make('hash')
                                            ->label('Hash')
                                            ->disabled()
                                            ->dehydrated(false),

                                        Forms\Components\TextInput::make('width')
                                            ->label('Szélesség')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated(false),

                                        Forms\Components\TextInput::make('height')
                                            ->label('Magasság')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated(false),
                                    ])
                                    ->columns(2)
                                    ->collapsed(),
                            ]),

                        Tabs\Tab::make('Előzmények')
                            ->badge(fn ($record) => $record?->versions()->count() ?? 0)
                            ->schema([
                                Section::make('Képcsere előzmények')
                                    ->description('Az utolsó 20 képcsere látható')
                                    ->schema([
                                        Forms\Components\Placeholder::make('version_list')
                                            ->label('')
                                            ->content(function ($record) {
                                                $versions = $record?->versions()->with(['replacedBy'])->get() ?? collect();
                                                
                                                // Get the latest restored version
                                                $latestRestored = $record?->versions()
                                                    ->where('is_restored', true)
                                                    ->latest()
                                                    ->first();
                                                
                                                if ($versions->isEmpty()) {
                                                    return new \Illuminate\Support\HtmlString('
                                                        <div class="text-center py-8">
                                                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                                                            </svg>
                                                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Nincs még előzmény</p>
                                                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Az első képcsere után itt fognak megjelenni a verziók</p>
                                                        </div>
                                                    ');
                                                }
                                                
                                                // Lightbox script
                                                $lightboxScript = <<<'JS'
                                                <script>
                                                window.openLightbox = function(imageUrl) {
                                                    let modal = document.getElementById("lightbox-modal");
                                                    if (!modal) {
                                                        modal = document.createElement("div");
                                                        modal.id = "lightbox-modal";
                                                        modal.className = "fixed inset-0 z-[9999] overflow-y-auto";
                                                        modal.innerHTML = `
                                                            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                                                                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" onclick="document.getElementById('lightbox-modal').remove(); document.body.style.overflow='auto';"></div>
                                                                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                                                                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                                                                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                                                        <div class="flex justify-end mb-2">
                                                                            <button type="button" onclick="document.getElementById('lightbox-modal').remove(); document.body.style.overflow='auto';" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                                                                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                                                </svg>
                                                                            </button>
                                                                        </div>
                                                                        <img src="${imageUrl}" alt="Preview" class="w-full h-auto" />
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        `;
                                                        document.body.appendChild(modal);
                                                        document.body.style.overflow = "hidden";
                                                    } else {
                                                        modal.querySelector("img").src = imageUrl;
                                                        modal.classList.remove("hidden");
                                                        document.body.style.overflow = "hidden";
                                                    }
                                                };
                                                </script>
                                                JS;
                                                
                                                $html = $lightboxScript . '<div class="space-y-4">';
                                                foreach ($versions as $version) {
                                                    $versionMedia = $version->media();
                                                    $html .= '
                                                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800">
                                                            <div class="flex flex-row gap-4 items-center">
                                                                <div class="flex-shrink-0">';
                                                    
                                                    if ($versionMedia) {
                                                        $html .= '<img src="'.$versionMedia->getUrl('thumb').'" alt="'.$version->original_filename.'" class="w-32 h-auto sm:h-32 object-contain sm:object-cover sm:object-top rounded-md border border-gray-300 dark:border-gray-600 cursor-pointer hover:opacity-75 transition" onclick="openLightbox(\''.$versionMedia->getUrl('preview').'\')" />';
                                                    } else {
                                                        $html .= '<div class="w-32 h-32 bg-gray-100 dark:bg-gray-700 rounded-md flex items-center justify-center">
                                                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                            </svg>
                                                        </div>';
                                                    }
                                                    
                                                    $html .= '</div>
                                                                <div class="flex-1 space-y-2">
                                                                    <div>
                                                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Eredeti fájlnév:</span>
                                                                        <div class="text-sm text-gray-900 dark:text-gray-100 break-all">'.($version->original_filename ?? 'N/A').'</div>
                                                                    </div>
                                                                    <div>
                                                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Cserélve:</span>
                                                                        <span class="text-sm text-gray-900 dark:text-gray-100">'.$version->created_at->format('Y-m-d H:i').'</span>';
                                                    
                                                    if ($version->replacedBy) {
                                                        $html .= '<span class="text-sm text-gray-500 dark:text-gray-400"> by '.$version->replacedBy->name.'</span>';
                                                    }
                                                    
                                                    $html .= '</div>';
                                                    
                                                    if ($version->reason) {
                                                        $html .= '<div>
                                                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Indoklás:</span>
                                                                            <p class="text-sm text-gray-900 dark:text-gray-100 mt-1">'.$version->reason.'</p>
                                                                        </div>';
                                                    } else {
                                                        $html .= '<div>
                                                                            <span class="text-sm text-gray-500 dark:text-gray-400 italic">Nincs indoklás</span>
                                                                        </div>';
                                                    }
                                                    
                                                    if ($version->is_restored && $latestRestored?->id === $version->id) {
                                                        $html .= '<div>
                                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                                                </svg>
                                                                                Visszaállítva
                                                                            </span>
                                                                        </div>';
                                                    }
                                                    
                                                    if ($version->width && $version->height) {
                                                        $html .= '<div>
                                                                            <span class="text-sm text-gray-500 dark:text-gray-400">'.$version->width.' × '.$version->height.' px</span>
                                                                        </div>';
                                                    }
                                                    
                                                    $html .= '</div>
                                                                <div class="flex-shrink-0">
                                                    
                                                    <button type="button" wire:click="$dispatch(\'restore-version\', { versionId: '.$version->id.' })" onclick="if(!confirm(\'Biztosan visszaállítod ezt a verziót? A jelenlegi kép előzményekbe kerül.\')) return false;" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                                                        </svg>
                                                                        Visszaállítás
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>';
                                                }
                                                $html .= '</div>';
                                                
                                                return new \Illuminate\Support\HtmlString($html);
                                            })
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->hidden(fn ($record) => $record === null),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('path')
                    ->label('Előnézet')
                    ->disk('public')
                    ->size(80)
                    ->extraImgAttributes([
                        'class' => 'cursor-zoom-in rounded-md border border-gray-200 dark:border-gray-700',
                    ])
                    ->action(LightboxPreviewableAction::make()),

                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('album.title')
                    ->label('Album')
                    ->sortable()
                    ->searchable()
                    ->badge(),

                Tables\Columns\TextColumn::make('assignedUser.name')
                    ->label('Hozzárendelve')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color(fn ($record) => $record->assigned_user_id ? 'success' : 'warning')
                    ->default('Jelöletlen'),

                Tables\Columns\TextColumn::make('notes_count')
                    ->label('Megjegyzések')
                    ->counts('notes')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Feltöltve')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('album_id')
                    ->label('Album')
                    ->relationship('album', 'title')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('assigned_user_id')
                    ->label('Hozzárendelt felhasználó')
                    ->relationship('assignedUser', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\Filter::make('unassigned')
                    ->label('Csak jelöletlenek')
                    ->query(fn (Builder $query) => $query->whereNull('assigned_user_id')),
            ])
            ->actions([
                ActionGroup::make([
                    LightboxPreviewableAction::make()
                        ->label('Megtekintés')
                        ->icon('heroicon-o-photo'),
                    Action::make('apply_watermark_single')
                        ->label('Vízjel alkalmazása')
                        ->icon('heroicon-o-sparkles')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Vízjel alkalmazása')
                        ->modalDescription('A képre vízjel kerül alkalmazásra. Ha már vízjelezett, újra vízjelezve lesz.')
                        ->modalSubmitActionLabel('Vízjelezés')
                        ->action(function (Photo $record, WatermarkService $watermarkService) {
                            try {
                                // Check watermark settings
                                $watermarkEnabled = Setting::get('watermark_enabled', true);
                                $watermarkText = Setting::get('watermark_text', 'Tablokirály');

                                if (! $watermarkEnabled || ! $watermarkText) {
                                    Notification::make()
                                        ->title('Vízjel kikapcsolva')
                                        ->body('A vízjelezés ki van kapcsolva a beállításokban.')
                                        ->warning()
                                        ->send();
                                    return;
                                }

                                $media = $record->getFirstMedia('photo');
                                if (! $media) {
                                    Notification::make()
                                        ->title('Hiba')
                                        ->body('A képhez nem tartozik media fájl.')
                                        ->danger()
                                        ->send();
                                    return;
                                }

                                // Check if preview exists
                                if (! $media->hasGeneratedConversion('preview')) {
                                    Notification::make()
                                        ->title('Hiba')
                                        ->body('A preview conversion még nem készült el. Próbáld újra később.')
                                        ->warning()
                                        ->send();
                                    return;
                                }

                                $previewPath = $media->getPath('preview');
                                if (! file_exists($previewPath)) {
                                    Notification::make()
                                        ->title('Hiba')
                                        ->body('A preview fájl nem található.')
                                        ->danger()
                                        ->send();
                                    return;
                                }

                                // Apply watermark
                                $watermarkService->applyTiledWatermark($previewPath, $watermarkText);

                                // Mark as watermarked
                                $media->setCustomProperty('watermarked', true);
                                $media->save();

                                Notification::make()
                                    ->title('Vízjel alkalmazva')
                                    ->body('A képre sikeresen alkalmazva a vízjel.')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Hiba történt')
                                    ->body('Nem sikerült a vízjel alkalmazása: ' . $e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    EditAction::make()
                        ->label('Szerkesztés'),
                    DeleteAction::make()
                        ->label('Törlés'),
                ])
                    ->label('Műveletek')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('apply_watermark')
                        ->label('Kijelöltek vízjelezése')
                        ->icon('heroicon-o-sparkles')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Kijelölt képek vízjelezése')
                        ->modalDescription('A kijelölt képekre vízjel kerül alkalmazásra. Ez szinkron művelet, várj a befejezésig!')
                        ->modalSubmitActionLabel('Vízjelezés')
                        ->action(function (Collection $records, WatermarkService $watermarkService) {
                            try {
                                // Check watermark settings
                                $watermarkEnabled = Setting::get('watermark_enabled', true);
                                $watermarkText = Setting::get('watermark_text', 'Tablokirály');

                                if (! $watermarkEnabled || ! $watermarkText) {
                                    Notification::make()
                                        ->title('Vízjel kikapcsolva')
                                        ->body('A vízjelezés ki van kapcsolva a beállításokban.')
                                        ->warning()
                                        ->send();
                                    return;
                                }

                                $processedCount = 0;
                                $skippedCount = 0;
                                $errorCount = 0;

                                foreach ($records as $photo) {
                                    try {
                                        $media = $photo->getFirstMedia('photo');
                                        if (! $media) {
                                            $skippedCount++;
                                            continue;
                                        }

                                        // Check if preview exists
                                        if (! $media->hasGeneratedConversion('preview')) {
                                            $skippedCount++;
                                            continue;
                                        }

                                        $previewPath = $media->getPath('preview');
                                        if (! file_exists($previewPath)) {
                                            $skippedCount++;
                                            continue;
                                        }

                                        // Apply watermark
                                        $watermarkService->applyTiledWatermark($previewPath, $watermarkText);

                                        // Mark as watermarked
                                        $media->setCustomProperty('watermarked', true);
                                        $media->save();

                                        $processedCount++;
                                    } catch (\Exception $e) {
                                        $errorCount++;
                                        \Log::error('Failed to apply watermark in bulk action', [
                                            'photo_id' => $photo->id,
                                            'error' => $e->getMessage(),
                                        ]);
                                    }
                                }

                                $message = "Vízjelezve: {$processedCount} kép.";
                                if ($skippedCount > 0) {
                                    $message .= " Kihagyva: {$skippedCount}.";
                                }
                                if ($errorCount > 0) {
                                    $message .= " Hiba: {$errorCount}.";
                                }

                                Notification::make()
                                    ->title('Vízjelezés befejezve')
                                    ->body($message)
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Hiba történt')
                                    ->body('Nem sikerült a vízjelek alkalmazása: ' . $e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListPhotos::route('/'),
            'view' => Pages\ViewPhoto::route('/{record}'),
            'edit' => Pages\EditPhoto::route('/{record}/edit'),
        ];
    }
}

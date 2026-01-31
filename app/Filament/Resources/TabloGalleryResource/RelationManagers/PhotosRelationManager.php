<?php

namespace App\Filament\Resources\TabloGalleryResource\RelationManagers;

use App\Filament\Resources\TabloGalleryResource;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $title = 'Fotók';

    protected static ?string $modelLabel = 'Fotó';

    protected static ?string $pluralModelLabel = 'Fotók';

    protected static \BackedEnum|string|null $icon = 'heroicon-o-photo';

    /**
     * KÖTELEZŐ HasGranularPermissions trait miatt!
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return TabloGalleryResource::canAccessRelation('photos');
    }

    /**
     * Badge showing count of photos.
     */
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->getMedia('photos')->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * Badge color - info for photos.
     */
    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'info';
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('collection_name', 'photos'))
            ->columns([
                Tables\Columns\ImageColumn::make('preview')
                    ->label('Előnézet')
                    ->state(fn (Media $record) => $record->getUrl('thumb'))
                    ->width(80)
                    ->height(80)
                    ->extraImgAttributes([
                        'style' => 'object-fit: cover; border-radius: 6px; cursor: pointer;',
                    ])
                    ->action(
                        Action::make('lightbox')
                            ->modalContent(function (Media $record) {
                                $allMedia = $this->getOwnerRecord()
                                    ->getMedia('photos')
                                    ->sortByDesc('created_at')
                                    ->values();

                                $currentIndex = $allMedia->search(fn ($m) => $m->id === $record->id);
                                $totalCount = $allMedia->count();

                                $mediaData = $allMedia->map(fn ($m) => [
                                    'id' => $m->id,
                                    'url' => $m->getUrl('preview'),
                                    'name' => $m->file_name,
                                ])->values()->toArray();

                                return view('components.media-lightbox', [
                                    'imageUrl' => $record->getUrl('preview'),
                                    'fileName' => $record->file_name,
                                    'currentIndex' => $currentIndex,
                                    'totalCount' => $totalCount,
                                    'mediaData' => $mediaData,
                                ]);
                            })
                            ->modalWidth('7xl')
                            ->modalHeading(fn (Media $record) => $record->file_name)
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Bezárás')
                    ),

                Tables\Columns\TextColumn::make('file_name')
                    ->label('Fájlnév')
                    ->formatStateUsing(function (string $state) {
                        $maxLength = 40;
                        if (strlen($state) <= $maxLength) {
                            return $state;
                        }
                        $ext = pathinfo($state, PATHINFO_EXTENSION);
                        $name = pathinfo($state, PATHINFO_FILENAME);
                        $keepChars = ($maxLength - strlen($ext) - 4) / 2;
                        return substr($name, 0, (int) $keepChars) . '...' . substr($name, -((int) $keepChars)) . '.' . $ext;
                    })
                    ->tooltip(fn (Media $record) => $record->file_name)
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('human_readable_size')
                    ->label('Méret')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Feltöltve')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Action::make('upload')
                    ->label('Fotók Feltöltése')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Forms\Components\FileUpload::make('photos')
                            ->label('Képek')
                            ->multiple()
                            ->image()
                            ->maxFiles(50)
                            ->maxSize(10240) // 10MB
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg', 'image/webp'])
                            ->preserveFilenames()
                            ->directory('temp-gallery-photos')
                            ->helperText('Max 50 kép, egyenként max 10 MB'),
                    ])
                    ->action(function (array $data) {
                        $gallery = $this->getOwnerRecord();
                        $uploadedCount = 0;

                        foreach ($data['photos'] ?? [] as $path) {
                            // Először próbáljuk a storage/app/ alatt (livewire temp)
                            $fullPath = storage_path('app/' . $path);
                            if (! file_exists($fullPath)) {
                                // Ha nem található, próbáljuk a storage/app/public/ alatt
                                $fullPath = storage_path('app/public/' . $path);
                            }

                            if (file_exists($fullPath)) {
                                $gallery->addMedia($fullPath)
                                    ->toMediaCollection('photos');
                                $uploadedCount++;
                            }
                        }

                        if ($uploadedCount > 0) {
                            Notification::make()
                                ->title('Fotók feltöltve')
                                ->body("{$uploadedCount} kép sikeresen hozzáadva a galériához.")
                                ->success()
                                ->send();
                        }
                    })
                    ->modalHeading('Fotók feltöltése')
                    ->modalWidth('4xl'),

                Action::make('deleteAll')
                    ->label('Összes törlése')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Összes fotó törlése')
                    ->modalDescription('Biztosan törölni szeretnéd az összes fotót ebből a galériából? Ez a művelet nem vonható vissza!')
                    ->modalSubmitActionLabel('Igen, törlöm mind')
                    ->action(function () {
                        $gallery = $this->getOwnerRecord();
                        $count = $gallery->getMedia('photos')->count();

                        $gallery->clearMediaCollection('photos');

                        Notification::make()
                            ->title('Fotók törölve')
                            ->body("{$count} fotó sikeresen törölve a galériából.")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Action::make('download')
                    ->label('Letöltés')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Media $record) => $record->getUrl())
                    ->openUrlInNewTab(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

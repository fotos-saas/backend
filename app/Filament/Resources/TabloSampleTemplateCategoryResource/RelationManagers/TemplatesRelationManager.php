<?php

namespace App\Filament\Resources\TabloSampleTemplateCategoryResource\RelationManagers;

use App\Filament\Resources\TabloSampleTemplateCategoryResource;
use App\Models\TabloSampleTemplate;
use App\Models\TabloSampleTemplateCategory;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class TemplatesRelationManager extends RelationManager
{
    protected static string $relationship = 'templates';

    protected static ?string $title = 'Minta Táblók';

    protected static ?string $modelLabel = 'Minta Tabló';

    protected static ?string $pluralModelLabel = 'Minta Táblók';

    protected static \BackedEnum|string|null $icon = 'heroicon-o-photo';

    /**
     * Determine if the relation manager can be viewed for the given record.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return TabloSampleTemplateCategoryResource::canAccessRelation('templates');
    }

    /**
     * Slugify filename while preserving extension.
     */
    private static function slugifyFilename(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);

        $slug = Str::slug($name);

        if (empty($slug)) {
            $slug = 'template-' . uniqid();
        }

        return $slug . '.' . strtolower($extension);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Minta adatok')
                    ->components([
                        Forms\Components\TextInput::make('name')
                            ->label('Név')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('pl. Elegáns kék')
                            ->helperText('A minta neve')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),

                        Forms\Components\Hidden::make('slug'),

                        Forms\Components\Textarea::make('description')
                            ->label('Leírás')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Opcionális leírás a mintáról'),

                        Forms\Components\TagsInput::make('tags')
                            ->label('Címkék')
                            ->placeholder('pl. új, népszerű')
                            ->helperText('Extra címkék (Enter-rel válassz el)')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sorrend')
                            ->numeric()
                            ->default(0)
                            ->helperText('Alacsonyabb = előrébb'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktív')
                            ->default(true)
                            ->helperText('Inaktív minta nem jelenik meg'),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Kiemelt')
                            ->default(false)
                            ->helperText('Kiemelt minta előrébb jelenik meg'),
                    ]),

                Section::make('Kép')
                    ->description('A minta megjelenítési képe')
                    ->components([
                        Forms\Components\FileUpload::make('image_path')
                            ->label('Minta kép')
                            ->image()
                            ->required()
                            ->directory('tablo-sample-templates')
                            ->preserveFilenames()
                            ->imageEditor()
                            ->imagePreviewHeight('200')
                            ->columnSpanFull()
                            ->helperText('Javasolt méret: 1200x900px'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width('50px'),

                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Kép')
                    ->disk('public')
                    ->width(80)
                    ->height(60),

                Tables\Columns\TextColumn::make('name')
                    ->label('Név')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Leírás')
                    ->limit(50)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Kiemelt')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktív')
                    ->boolean(),

                Tables\Columns\TextColumn::make('projects_count')
                    ->label('Választások')
                    ->counts('projects')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                // Tömeges képfeltöltés bővített mezőkkel
                Action::make('uploadTemplates')
                    ->label('Képek feltöltése')
                    ->icon('heroicon-o-photo')
                    ->color('primary')
                    ->form([
                        Forms\Components\FileUpload::make('images')
                            ->label('Minta képek vagy ZIP fájl')
                            ->multiple()
                            ->acceptedFileTypes(['image/*', 'application/zip', 'application/x-zip-compressed'])
                            ->maxSize(102400)
                            ->storeFiles(false)
                            ->imageEditor(false)
                            ->imagePreviewHeight('60')
                            ->helperText('Képeket vagy egy ZIP fájlt tölthetsz fel. A fájlnévből generálódik a minta neve.'),

                        Forms\Components\Textarea::make('description')
                            ->label('Leírás (minden mintához)')
                            ->rows(2)
                            ->maxLength(500)
                            ->placeholder('Opcionális közös leírás...'),

                        Forms\Components\TagsInput::make('tags')
                            ->label('Címkék (minden mintához)')
                            ->placeholder('pl. új, népszerű'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktív')
                            ->default(true),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Kiemelt')
                            ->default(false),
                    ])
                    ->action(function (array $data) {
                        /** @var TabloSampleTemplateCategory $category */
                        $category = $this->getOwnerRecord();
                        $uploadedCount = 0;
                        $maxSortOrder = $category->templates()->max('sort_order') ?? 0;

                        // Közös beállítások
                        $commonData = [
                            'description' => $data['description'] ?? null,
                            'tags' => $data['tags'] ?? [],
                            'is_active' => $data['is_active'] ?? true,
                            'is_featured' => $data['is_featured'] ?? false,
                        ];

                        foreach ($data['images'] ?? [] as $file) {
                            if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                                $originalName = $file->getClientOriginalName();

                                if (str_ends_with(strtolower($originalName), '.zip')) {
                                    $tempPath = sys_get_temp_dir() . '/' . $originalName;
                                    copy($file->getRealPath(), $tempPath);

                                    $zip = new ZipArchive();
                                    if ($zip->open($tempPath) === true) {
                                        $tempDir = sys_get_temp_dir() . '/sample_templates_' . uniqid();
                                        mkdir($tempDir, 0755, true);
                                        $zip->extractTo($tempDir);
                                        $zip->close();

                                        $iterator = new \RecursiveIteratorIterator(
                                            new \RecursiveDirectoryIterator($tempDir)
                                        );
                                        foreach ($iterator as $fileInfo) {
                                            if ($fileInfo->isFile() && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $fileInfo->getFilename())) {
                                                if (str_starts_with($fileInfo->getFilename(), '.') || str_contains($fileInfo->getPathname(), '__MACOSX')) {
                                                    continue;
                                                }

                                                $maxSortOrder++;
                                                $this->createTemplateFromFile(
                                                    $category,
                                                    $fileInfo->getPathname(),
                                                    $fileInfo->getFilename(),
                                                    $maxSortOrder,
                                                    $commonData
                                                );
                                                $uploadedCount++;
                                            }
                                        }

                                        $this->recursiveDelete($tempDir);
                                    }
                                    @unlink($tempPath);
                                } else {
                                    $maxSortOrder++;

                                    $slugFilename = self::slugifyFilename($originalName);
                                    $storagePath = 'tablo-sample-templates/' . $slugFilename;

                                    $counter = 1;
                                    while (Storage::disk('public')->exists($storagePath)) {
                                        $pathInfo = pathinfo($slugFilename);
                                        $storagePath = 'tablo-sample-templates/' . $pathInfo['filename'] . '-' . $counter . '.' . $pathInfo['extension'];
                                        $counter++;
                                    }

                                    Storage::disk('public')->put($storagePath, $file->get());

                                    $nameFromFile = pathinfo($originalName, PATHINFO_FILENAME);
                                    $name = ucfirst(str_replace(['-', '_'], ' ', $nameFromFile));

                                    $template = TabloSampleTemplate::create([
                                        'name' => $name,
                                        'slug' => Str::slug($name) . '-' . uniqid(),
                                        'image_path' => $storagePath,
                                        'sort_order' => $maxSortOrder,
                                        'description' => $commonData['description'],
                                        'tags' => $commonData['tags'],
                                        'is_active' => $commonData['is_active'],
                                        'is_featured' => $commonData['is_featured'],
                                    ]);

                                    $template->categories()->attach($category->id);
                                    $uploadedCount++;
                                }
                            }
                        }

                        Notification::make()
                            ->title("{$uploadedCount} minta feltöltve")
                            ->success()
                            ->send();
                    }),

                CreateAction::make()
                    ->label('Új Minta'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    // Csoportos szerkesztés
                    BulkAction::make('bulkEdit')
                        ->label('Csoportos szerkesztés')
                        ->icon('heroicon-o-pencil-square')
                        ->form([
                            Forms\Components\Textarea::make('description')
                                ->label('Leírás')
                                ->rows(2)
                                ->maxLength(500)
                                ->placeholder('Új leírás (üresen hagyva nem változik)'),

                            Forms\Components\TagsInput::make('tags')
                                ->label('Címkék')
                                ->placeholder('Új címkék (üresen hagyva nem változik)'),

                            Forms\Components\Select::make('is_active')
                                ->label('Aktív')
                                ->options([
                                    '' => '-- Ne változtasd --',
                                    '1' => 'Igen',
                                    '0' => 'Nem',
                                ])
                                ->default(''),

                            Forms\Components\Select::make('is_featured')
                                ->label('Kiemelt')
                                ->options([
                                    '' => '-- Ne változtasd --',
                                    '1' => 'Igen',
                                    '0' => 'Nem',
                                ])
                                ->default(''),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $updatedCount = 0;

                            foreach ($records as $record) {
                                $updateData = [];

                                if (! empty($data['description'])) {
                                    $updateData['description'] = $data['description'];
                                }

                                if (! empty($data['tags'])) {
                                    $updateData['tags'] = $data['tags'];
                                }

                                if ($data['is_active'] !== '' && $data['is_active'] !== null) {
                                    $updateData['is_active'] = (bool) $data['is_active'];
                                }

                                if ($data['is_featured'] !== '' && $data['is_featured'] !== null) {
                                    $updateData['is_featured'] = (bool) $data['is_featured'];
                                }

                                if (! empty($updateData)) {
                                    $record->update($updateData);
                                    $updatedCount++;
                                }
                            }

                            Notification::make()
                                ->title("{$updatedCount} minta frissítve")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Kiemeltté tétel
                    BulkAction::make('markFeatured')
                        ->label('Kiemeltté tétel')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->action(function (Collection $records): void {
                            $records->each->update(['is_featured' => true]);

                            Notification::make()
                                ->title("{$records->count()} minta kiemeltté téve")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Kiemelés megszüntetése
                    BulkAction::make('unmarkFeatured')
                        ->label('Kiemelés megszüntetése')
                        ->icon('heroicon-o-star')
                        ->color('gray')
                        ->action(function (Collection $records): void {
                            $records->each->update(['is_featured' => false]);

                            Notification::make()
                                ->title("{$records->count()} minta kiemelése megszüntetve")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Aktiválás
                    BulkAction::make('activate')
                        ->label('Aktiválás')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (Collection $records): void {
                            $records->each->update(['is_active' => true]);

                            Notification::make()
                                ->title("{$records->count()} minta aktiválva")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Deaktiválás
                    BulkAction::make('deactivate')
                        ->label('Deaktiválás')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function (Collection $records): void {
                            $records->each->update(['is_active' => false]);

                            Notification::make()
                                ->title("{$records->count()} minta deaktiválva")
                                ->warning()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make()
                        ->label('Kijelöltek törlése'),
                ])->label('Csoportos műveletek'),
            ]);
    }

    /**
     * Create a template from an image file.
     */
    private function createTemplateFromFile(
        TabloSampleTemplateCategory $category,
        string $filePath,
        string $filename,
        int $sortOrder,
        array $commonData = []
    ): void {
        $slugFilename = self::slugifyFilename($filename);
        $storagePath = 'tablo-sample-templates/' . $slugFilename;

        $counter = 1;
        while (Storage::disk('public')->exists($storagePath)) {
            $pathInfo = pathinfo($slugFilename);
            $storagePath = 'tablo-sample-templates/' . $pathInfo['filename'] . '-' . $counter . '.' . $pathInfo['extension'];
            $counter++;
        }

        Storage::disk('public')->put($storagePath, file_get_contents($filePath));

        $nameFromFile = pathinfo($filename, PATHINFO_FILENAME);
        $name = ucfirst(str_replace(['-', '_'], ' ', $nameFromFile));

        $template = TabloSampleTemplate::create([
            'name' => $name,
            'slug' => Str::slug($name) . '-' . uniqid(),
            'image_path' => $storagePath,
            'sort_order' => $sortOrder,
            'description' => $commonData['description'] ?? null,
            'tags' => $commonData['tags'] ?? [],
            'is_active' => $commonData['is_active'] ?? true,
            'is_featured' => $commonData['is_featured'] ?? false,
        ]);

        $template->categories()->attach($category->id);
    }

    /**
     * Recursively delete a directory.
     */
    private function recursiveDelete(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    $path = $dir . '/' . $object;
                    if (is_dir($path)) {
                        $this->recursiveDelete($path);
                    } else {
                        @unlink($path);
                    }
                }
            }
            @rmdir($dir);
        }
    }
}

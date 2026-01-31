<?php

namespace App\Filament\Resources\TabloProjectResource\RelationManagers;

use App\Filament\Resources\TabloProjectResource;
use App\Models\ProjectEmail;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SamplesRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    /**
     * Determine if the relation manager can be viewed for the given record.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return TabloProjectResource::canAccessRelation('samples');
    }

    /**
     * Badge showing count of samples.
     */
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->getMedia('samples')->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * Badge color - success for samples.
     */
    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'success';
    }

    protected static ?string $title = 'Minták';

    protected static ?string $modelLabel = 'Minta';

    protected static ?string $pluralModelLabel = 'Minták';

    protected static \BackedEnum|string|null $icon = 'heroicon-o-photo';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('collection_name', 'samples'))
            ->columns([
                Tables\Columns\ImageColumn::make('preview')
                    ->label('Előnézet')
                    ->state(fn (Media $record) => $record->getUrl('thumb'))
                    ->width(80)
                    ->height(80)
                    ->action(
                        Action::make('lightbox')
                            ->modalContent(function (Media $record) {
                                $allMedia = $this->getOwnerRecord()
                                    ->getMedia('samples')
                                    ->sortByDesc('created_at')
                                    ->values();

                                $currentIndex = $allMedia->search(fn ($m) => $m->id === $record->id);
                                $totalCount = $allMedia->count();

                                $mediaData = $allMedia->map(fn ($m) => [
                                    'id' => $m->id,
                                    'url' => $m->getUrl(),
                                    'name' => $m->file_name,
                                ])->values()->toArray();

                                return view('components.media-lightbox', [
                                    'imageUrl' => $record->getUrl(),
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
                        $maxLength = 35;
                        if (strlen($state) <= $maxLength) {
                            return $state;
                        }
                        $ext = pathinfo($state, PATHINFO_EXTENSION);
                        $name = pathinfo($state, PATHINFO_FILENAME);
                        $keepChars = ($maxLength - strlen($ext) - 4) / 2; // 4 = "..." + "."
                        return substr($name, 0, (int) $keepChars) . '...' . substr($name, -((int) $keepChars)) . '.' . $ext;
                    })
                    ->tooltip(fn (Media $record) => $record->file_name)
                    ->searchable()
                    ->sortable()
                    ->description(fn (Media $record) => $record->getCustomProperty('description')
                        ? new HtmlString('<span style="color: #6b7280; font-size: 12px;">' . e(mb_substr(strip_tags($record->getCustomProperty('description')), 0, 50)) . (mb_strlen(strip_tags($record->getCustomProperty('description'))) > 50 ? '...' : '') . '</span>')
                        : null
                    ),

                Tables\Columns\TextColumn::make('human_readable_size')
                    ->label('Méret')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktív')
                    ->state(fn (Media $record) => $record->getCustomProperty('is_active', true))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->action(
                        Action::make('toggleActive')
                            ->label(fn (Media $record) => $record->getCustomProperty('is_active', true) ? 'Inaktiválás' : 'Aktiválás')
                            ->icon(fn (Media $record) => $record->getCustomProperty('is_active', true) ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                            ->color(fn (Media $record) => $record->getCustomProperty('is_active', true) ? 'danger' : 'success')
                            ->requiresConfirmation()
                            ->modalHeading(fn (Media $record) => $record->getCustomProperty('is_active', true) ? 'Minta inaktiválása' : 'Minta aktiválása')
                            ->modalDescription(fn (Media $record) => $record->getCustomProperty('is_active', true)
                                ? 'Az inaktív minták nem jelennek meg a felhasználóknak.'
                                : 'Az aktív minták láthatóak lesznek a felhasználóknak.')
                            ->action(function (Media $record) {
                                $currentValue = $record->getCustomProperty('is_active', true);
                                $record->setCustomProperty('is_active', ! $currentValue);
                                $record->save();
                            })
                    ),

                Tables\Columns\TextColumn::make('sent_emails')
                    ->label('Elküldve')
                    ->state(function (Media $record) {
                        /** @var \App\Models\TabloProject $project */
                        $project = $this->getOwnerRecord();

                        // Keresés a projekt emailjeiben, ahol a media_id egyezik
                        $emails = ProjectEmail::where('tablo_project_id', $project->id)
                            ->where('direction', 'outbound')
                            ->whereNotNull('attachments')
                            ->get()
                            ->filter(function ($email) use ($record) {
                                $attachments = $email->attachments ?? [];
                                foreach ($attachments as $attachment) {
                                    if (($attachment['media_id'] ?? null) == $record->id) {
                                        return true;
                                    }
                                }

                                return false;
                            });

                        if ($emails->isEmpty()) {
                            return null;
                        }

                        return $emails->count();
                    })
                    ->formatStateUsing(function ($state, Media $record) {
                        if (! $state) {
                            return new HtmlString('<span style="color: #9ca3af;">-</span>');
                        }

                        return new HtmlString(
                            '<span style="background: #10b981; color: white; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600;">'
                            . $state . '× elküldve</span>'
                        );
                    })
                    ->html()
                    ->action(
                        Action::make('showSentEmails')
                            ->modalHeading('Minta elküldési előzmények')
                            ->modalWidth('2xl')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Bezárás')
                            ->modalContent(function (Media $record) {
                                /** @var \App\Models\TabloProject $project */
                                $project = $this->getOwnerRecord();

                                $emails = ProjectEmail::where('tablo_project_id', $project->id)
                                    ->where('direction', 'outbound')
                                    ->whereNotNull('attachments')
                                    ->orderBy('email_date', 'desc')
                                    ->get()
                                    ->filter(function ($email) use ($record) {
                                        $attachments = $email->attachments ?? [];
                                        foreach ($attachments as $attachment) {
                                            if (($attachment['media_id'] ?? null) == $record->id) {
                                                return true;
                                            }
                                        }

                                        return false;
                                    });

                                if ($emails->isEmpty()) {
                                    return new HtmlString('<p style="color: #6b7280; text-align: center; padding: 20px;">Ez a minta még nem lett elküldve emailben.</p>');
                                }

                                $html = '<div style="display: flex; flex-direction: column; gap: 16px;">';
                                foreach ($emails as $email) {
                                    $bodyPreview = $email->clean_body_html
                                        ? strip_tags($email->clean_body_html)
                                        : ($email->clean_body_text ?? '');
                                    $bodyPreview = mb_substr(trim($bodyPreview), 0, 200);
                                    if (mb_strlen(trim(strip_tags($email->clean_body_html ?? $email->clean_body_text ?? ''))) > 200) {
                                        $bodyPreview .= '...';
                                    }

                                    $html .= '<div style="background: #f9fafb; padding: 12px; border-radius: 8px; border-left: 4px solid #10b981;">';
                                    $html .= '<div style="font-weight: 600; margin-bottom: 4px;">' . e($email->subject) . '</div>';
                                    $html .= '<div style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">';
                                    $html .= '<strong>Címzett:</strong> ' . e($email->to_email) . ' &nbsp;|&nbsp; ';
                                    $html .= '<span title="' . $email->email_date->format('Y-m-d H:i') . '">' . $email->email_date->diffForHumans() . '</span>';
                                    $html .= '</div>';
                                    if ($bodyPreview) {
                                        $html .= '<div style="font-size: 13px; color: #374151; background: white; padding: 8px; border-radius: 4px;">' . e($bodyPreview) . '</div>';
                                    }
                                    $html .= '</div>';
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            })
                    ),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Feltöltve')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Action::make('upload')
                    ->label('Minták Feltöltése')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Forms\Components\FileUpload::make('samples')
                            ->label('Képek')
                            ->multiple()
                            ->image()
                            ->maxFiles(20)
                            ->maxSize(10240)
                            ->preserveFilenames()
                            ->directory('temp-samples')
                            ->helperText('Max 20 kép, egyenként max 10 MB'),
                    ])
                    ->action(function (array $data) {
                        $project = $this->getOwnerRecord();

                        foreach ($data['samples'] ?? [] as $path) {
                            // Először próbáljuk a storage/app/ alatt (livewire temp)
                            $fullPath = storage_path('app/' . $path);
                            if (! file_exists($fullPath)) {
                                // Ha nem található, próbáljuk a storage/app/public/ alatt
                                $fullPath = storage_path('app/public/' . $path);
                            }

                            if (file_exists($fullPath)) {
                                $project->addMedia($fullPath)
                                    ->withCustomProperties(['is_active' => true])
                                    ->toMediaCollection('samples');
                            }
                        }
                    }),
            ])
            ->actions([
                Action::make('edit')
                    ->label('Szerkesztés')
                    ->icon('heroicon-o-pencil-square')
                    ->fillForm(fn (Media $record) => [
                        'description' => $record->getCustomProperty('description'),
                    ])
                    ->form([
                        Forms\Components\RichEditor::make('description')
                            ->label('Változások leírása')
                            ->helperText('Írd le, milyen változásokat kértek ezen a mintán')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'bulletList',
                                'orderedList',
                                'link',
                                'undo',
                                'redo',
                            ])
                            ->extraInputAttributes(['style' => 'min-height: 250px;'])
                            ->columnSpanFull(),
                    ])
                    ->modalHeading('Minta szerkesztése')
                    ->modalWidth('2xl')
                    ->modalSubmitActionLabel('Mentés')
                    ->action(function (Media $record, array $data) {
                        $record->setCustomProperty('description', $data['description']);
                        $record->save();
                    }),
                Action::make('download')
                    ->label('Letöltés')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Media $record) => $record->getUrl())
                    ->openUrlInNewTab(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\TabloMissingPeople\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class TabloMissingPersonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->components([
                        // Személy infó + jelenlegi kép
                        Placeholder::make('szemely_info')
                            ->label('Személy adatai')
                            ->content(function ($record) {
                                if (!$record) {
                                    return '';
                                }

                                $name = e($record->name);
                                $type = $record->type === 'teacher' ? 'Tanár' : 'Diák';
                                $typeColor = $record->type === 'teacher' ? '#f59e0b' : '#3b82f6';
                                $project = e($record->project?->name ?? 'Ismeretlen projekt');

                                $photoHtml = '';
                                if ($record->photo) {
                                    $thumbUrl = e($record->photo_thumb_url ?? $record->photo->getUrl('thumb'));
                                    $fullUrl = e($record->photo->getUrl());
                                    $photoHtml = "
                                        <img src=\"{$thumbUrl}\"
                                             onclick=\"window.open('{$fullUrl}', '_blank', 'width=1200,height=900')\"
                                             style=\"width: 120px; height: 150px; object-fit: cover; object-position: top; border-radius: 12px; cursor: zoom-in; box-shadow: 0 4px 12px rgba(0,0,0,0.15);\" />
                                    ";
                                } else {
                                    $photoHtml = "
                                        <div style=\"width: 120px; height: 150px; background: #f3f4f6; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #9ca3af;\">
                                            <svg xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\" stroke-width=\"1.5\" stroke=\"currentColor\" style=\"width: 48px; height: 48px;\">
                                                <path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z\" />
                                            </svg>
                                        </div>
                                    ";
                                }

                                return new HtmlString("
                                    <div style=\"display: flex; align-items: flex-start; gap: 24px; padding: 16px 0;\">
                                        {$photoHtml}
                                        <div style=\"flex: 1;\">
                                            <div style=\"font-size: 24px; font-weight: 700; margin-bottom: 8px;\">{$name}</div>
                                            <div style=\"display: flex; align-items: center; gap: 8px; margin-bottom: 4px;\">
                                                <span style=\"width: 10px; height: 10px; background: {$typeColor}; border-radius: 50%;\"></span>
                                                <span style=\"color: #6b7280;\">{$type}</span>
                                            </div>
                                            <div style=\"color: #9ca3af; font-size: 14px;\">{$project}</div>
                                        </div>
                                    </div>
                                ");
                            })
                            ->columnSpanFull(),

                        // Megjegyzés
                        Textarea::make('note')
                            ->label('Megjegyzés')
                            ->rows(2)
                            ->columnSpanFull(),

                        // Kép feltöltése
                        FileUpload::make('upload_photo')
                            ->label('Új kép feltöltése')
                            ->image()
                            ->imageEditor(false)
                            ->storeFiles(false)
                            ->maxSize(10240) // 10MB
                            ->helperText('Húzd ide a képet vagy kattints a tallózáshoz')
                            ->columnSpanFull(),

                        // Kép törlése checkbox (csak ha van kép)
                        Checkbox::make('remove_photo')
                            ->label('Jelenlegi kép törlése')
                            ->visible(fn ($record) => $record?->photo !== null)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

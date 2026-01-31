<?php

namespace App\Filament\Resources\EmailEvents\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmailEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Esemény beállítások')
                    ->schema([
                        Forms\Components\Select::make('event_type')
                            ->label('Esemény típusa')
                            ->options([
                                'user_registered' => 'Felhasználó regisztrált',
                                'album_created' => 'Album létrejött',
                                'order_placed' => 'Megrendelés leadva',
                                'order_status_changed' => 'Megrendelés státusz változott',
                                'photo_uploaded' => 'Fotó feltöltve',
                                'password_reset' => 'Jelszó visszaállítás',
                                'manual' => 'Manuális küldés',
                            ])
                            ->required()
                            ->reactive()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('email_template_id')
                            ->label('Email sablon')
                            ->relationship('emailTemplate', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->helperText('Válaszd ki, melyik email sablont szeretnéd kiküldeni ennél az eseménynél.'),

                        Forms\Components\Select::make('recipient_type')
                            ->label('Címzett típusa')
                            ->options([
                                'user' => 'Érintett felhasználó',
                                'album_users' => 'Album összes felhasználója',
                                'order_user' => 'Megrendelő felhasználó',
                                'custom' => 'Egyedi email címek',
                            ])
                            ->required()
                            ->reactive()
                            ->helperText('Ki kapja meg az emailt?'),

                        Forms\Components\TagsInput::make('custom_recipients')
                            ->label('Egyedi címzettek')
                            ->placeholder('email@pelda.hu')
                            ->visible(fn (callable $get) => $get('recipient_type') === 'custom')
                            ->helperText('Add meg az email címeket, akiknek küldeni szeretnéd.'),

                        Forms\Components\FileUpload::make('attachments')
                            ->label('Mellékletek')
                            ->multiple()
                            ->directory('email-attachments')
                            ->visibility('private')
                            ->maxSize(10240)
                            ->helperText('Maximális fájlméret: 10MB. Ezek a fájlok minden kiküldött emailhez hozzá lesznek csatolva.')
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktív esemény')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Ha aktív, akkor automatikusan kiküldésre kerül az email az esemény bekövetkezésekor.'),
                    ])
                    ->columns(2),
            ]);
    }
}

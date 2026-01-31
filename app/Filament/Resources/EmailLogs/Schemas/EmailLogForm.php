<?php

namespace App\Filament\Resources\EmailLogs\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmailLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Email részletek')
                    ->schema([
                        Forms\Components\Placeholder::make('event_type')
                            ->label('Esemény típusa')
                            ->content(fn ($record) => $record ? match ($record->event_type) {
                                'user_registered' => 'Felhasználó regisztrált',
                                'album_created' => 'Album létrejött',
                                'order_placed' => 'Megrendelés leadva',
                                'order_status_changed' => 'Megrendelés státusz változott',
                                'photo_uploaded' => 'Fotó feltöltve',
                                'password_reset' => 'Jelszó visszaállítás',
                                'manual' => 'Manuális',
                                default => $record->event_type ?? '-',
                            } : '-'),

                        Forms\Components\Placeholder::make('emailTemplate.name')
                            ->label('Email sablon')
                            ->content(fn ($record) => $record?->emailTemplate?->name ?? '-'),

                        Forms\Components\Placeholder::make('recipient_email')
                            ->label('Címzett email')
                            ->content(fn ($record) => $record?->recipient_email ?? '-'),

                        Forms\Components\Placeholder::make('recipient.name')
                            ->label('Címzett név')
                            ->content(fn ($record) => $record?->recipient?->name ?? '-'),

                        Forms\Components\Placeholder::make('subject')
                            ->label('Tárgy')
                            ->content(fn ($record) => $record?->subject ?? '-')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('status')
                            ->label('Státusz')
                            ->content(fn ($record) => $record ? match ($record->status) {
                                'sent' => '✅ Elküldve',
                                'failed' => '❌ Sikertelen',
                                'queued' => '⏳ Sorban áll',
                                default => $record->status,
                            } : '-'),

                        Forms\Components\Placeholder::make('sent_at')
                            ->label('Elküldve')
                            ->content(fn ($record) => $record?->sent_at?->format('Y-m-d H:i:s') ?? '-'),
                    ])
                    ->columns(2),

                Section::make('Email tartalom')
                    ->schema([
                        Forms\Components\Placeholder::make('body')
                            ->label('Tartalom')
                            ->content(fn ($record) => new \Illuminate\Support\HtmlString(
                                '<div class="prose prose-sm max-w-none">'.($record?->body ?? '-').'</div>'
                            ))
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Hiba információk')
                    ->schema([
                        Forms\Components\Placeholder::make('error_message')
                            ->label('Hiba üzenet')
                            ->content(fn ($record) => $record?->error_message ?? 'Nincs hiba')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record && $record->status === 'failed')
                    ->collapsible(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\EmailLogs\Tables;

use App\Models\EmailLog;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;

class EmailLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Esemény')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'user_registered' => 'User reg.',
                        'album_created' => 'Album',
                        'order_placed' => 'Rendel',
                        'order_status_changed' => 'Státusz',
                        'photo_uploaded' => 'Fotó',
                        'password_reset' => 'Jelszó',
                        'manual' => 'Manuális',
                        default => $state ?? 'N/A',
                    })
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('recipient_email')
                    ->label('Címzett')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Tárgy')
                    ->limit(40)
                    ->tooltip(fn (EmailLog $record) => $record->subject)
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Státusz')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        'queued' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'sent' => 'Elküldve',
                        'failed' => 'Sikertelen',
                        'queued' => 'Sorban',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Elküldve')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Státusz')
                    ->options([
                        'sent' => 'Elküldve',
                        'failed' => 'Sikertelen',
                        'queued' => 'Sorban',
                    ]),

                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Esemény típusa')
                    ->options([
                        'user_registered' => 'Felhasználó regisztrált',
                        'album_created' => 'Album létrejött',
                        'order_placed' => 'Megrendelés leadva',
                        'order_status_changed' => 'Megrendelés státusz változott',
                        'photo_uploaded' => 'Fotó feltöltve',
                        'password_reset' => 'Jelszó visszaállítás',
                        'manual' => 'Manuális',
                    ]),

                Tables\Filters\Filter::make('sent_at')
                    ->form([
                        Forms\Components\DatePicker::make('sent_from')
                            ->label('Küldve -tól'),
                        Forms\Components\DatePicker::make('sent_until')
                            ->label('Küldve -ig'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['sent_from'], fn ($q, $date) => $q->whereDate('sent_at', '>=', $date))
                            ->when($data['sent_until'], fn ($q, $date) => $q->whereDate('sent_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }
}

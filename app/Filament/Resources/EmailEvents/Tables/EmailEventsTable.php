<?php

namespace App\Filament\Resources\EmailEvents\Tables;

use App\Models\EmailEvent;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

class EmailEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Esemény típusa')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'user_registered' => 'Felhasználó regisztrált',
                        'album_created' => 'Album létrejött',
                        'order_placed' => 'Megrendelés leadva',
                        'order_status_changed' => 'Megrendelés státusz változott',
                        'photo_uploaded' => 'Fotó feltöltve',
                        'password_reset' => 'Jelszó visszaállítás',
                        'manual' => 'Manuális',
                        default => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'user_registered' => 'success',
                        'album_created' => 'info',
                        'order_placed' => 'warning',
                        'order_status_changed' => 'primary',
                        'photo_uploaded' => 'gray',
                        'password_reset' => 'danger',
                        'manual' => 'secondary',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('emailTemplate.name')
                    ->label('Email sablon')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('recipient_type')
                    ->label('Címzett típus')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'user' => 'Érintett felhasználó',
                        'album_users' => 'Album felhasználói',
                        'order_user' => 'Megrendelő',
                        'custom' => 'Egyedi',
                        default => $state,
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktív')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Módosítva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
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

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktív státusz')
                    ->trueLabel('Csak aktív')
                    ->falseLabel('Csak inaktív'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(function ($query) {
                $newEventId = session('new_email_event_id');

                if ($newEventId) {
                    return $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$newEventId])
                        ->orderBy('created_at', 'desc');
                }

                return $query->orderBy('created_at', 'desc');
            })
            ->recordClasses(function (EmailEvent $record) {
                $createdAt = $record->created_at;
                $tenSecondsAgo = now()->subSeconds(10);

                return $createdAt && $createdAt->isAfter($tenSecondsAgo)
                    ? 'fi-ta-row-new'
                    : null;
            });
    }
}

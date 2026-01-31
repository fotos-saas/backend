<?php

namespace App\Filament\Resources\PaymentMethods\Tables;

use App\Models\PaymentMethod;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Megnevezés')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Típus')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'card' => 'Bankkártya',
                        'transfer' => 'Átutalás',
                        'cash' => 'Készpénz',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'card' => 'success',
                        'transfer' => 'info',
                        'cash' => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktív')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('Sorrend')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Frissítve')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(function ($query) {
                $newRecordId = session('new_payment_method_id');
                if ($newRecordId) {
                    return $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$newRecordId])
                        ->orderBy('sort_order')
                        ->orderBy('name');
                }

                return $query->orderBy('sort_order')->orderBy('name');
            })
            ->recordClasses(function (PaymentMethod $record) {
                $createdAt = $record->created_at;
                $tenSecondsAgo = now()->subSeconds(10);

                if ($createdAt && $createdAt->isAfter($tenSecondsAgo)) {
                    return 'fi-ta-row-new';
                }

                return null;
            })
            ->defaultSort('sort_order');
    }
}

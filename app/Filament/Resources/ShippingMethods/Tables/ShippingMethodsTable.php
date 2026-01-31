<?php

namespace App\Filament\Resources\ShippingMethods\Tables;

use App\Models\ShippingMethod;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShippingMethodsTable
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
                        'courier' => 'Futár',
                        'parcel_locker' => 'Csomagautomata',
                        'letter' => 'Levél',
                        'pickup' => 'Személyes átvétel',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'courier' => 'primary',
                        'parcel_locker' => 'success',
                        'letter' => 'info',
                        'pickup' => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('provider')
                    ->label('Szolgáltató')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'foxpost' => 'Foxpost',
                        'packeta' => 'Packeta',
                        'mpl' => 'Magyar Posta',
                        null => '—',
                        default => $state,
                    })
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktív')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('supports_cod')
                    ->label('Utánvét')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('cod_fee_huf')
                    ->label('Utánvét díj')
                    ->money('HUF')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('min_weight_grams')
                    ->label('Min. súly')
                    ->numeric()
                    ->suffix(' g')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('max_weight_grams')
                    ->label('Max. súly')
                    ->numeric()
                    ->suffix(' g')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                $newRecordId = session('new_shipping_method_id');
                if ($newRecordId) {
                    return $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$newRecordId])
                        ->orderBy('sort_order')
                        ->orderBy('name');
                }

                return $query->orderBy('sort_order')->orderBy('name');
            })
            ->recordClasses(function (ShippingMethod $record) {
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

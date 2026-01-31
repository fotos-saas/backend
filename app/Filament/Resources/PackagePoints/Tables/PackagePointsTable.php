<?php

namespace App\Filament\Resources\PackagePoints\Tables;

use App\Models\PackagePoint;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PackagePointsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provider')
                    ->label('Szolgáltató')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'foxpost' => 'Foxpost',
                        'packeta' => 'Packeta',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'foxpost' => 'warning',
                        'packeta' => 'success',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Név')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                TextColumn::make('city')
                    ->label('Város')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('zip')
                    ->label('Ir.szám')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('address')
                    ->label('Cím')
                    ->searchable()
                    ->limit(40)
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Aktív')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('last_synced_at')
                    ->label('Utolsó szinkronizálás')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),

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
                EditAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(function ($query) {
                $newRecordId = session('new_package_point_id');
                if ($newRecordId) {
                    return $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$newRecordId])
                        ->orderBy('city')
                        ->orderBy('name');
                }

                return $query->orderBy('city')->orderBy('name');
            })
            ->recordClasses(function (PackagePoint $record) {
                $createdAt = $record->created_at;
                $tenSecondsAgo = now()->subSeconds(10);

                if ($createdAt && $createdAt->isAfter($tenSecondsAgo)) {
                    return 'fi-ta-row-new';
                }

                return null;
            })
            ->defaultSort('city');
    }
}

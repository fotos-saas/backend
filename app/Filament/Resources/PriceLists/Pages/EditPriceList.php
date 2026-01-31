<?php

namespace App\Filament\Resources\PriceLists\Pages;

use App\Filament\Resources\PriceLists\PriceListResource;
use App\Models\Price;
use App\Models\PrintSize;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditPriceList extends EditRecord
{
    protected static string $resource = PriceListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addSizes')
                ->label('Méretek hozzáadása')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->form(function (Schema $schema) {
                    $existingPrintSizeIds = $this->record->prices()
                        ->pluck('print_size_id')
                        ->toArray();

                    return $schema->components([
                        Checkbox::make('add_all')
                            ->label('Összes hozzá nem adott méret hozzáadása')
                            ->helperText('Ha bejelölöd, az összes olyan méretet hozzáadja, ami még nem szerepel az árlistában.')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $set('print_size_ids', []);
                                }
                            }),

                        Select::make('print_size_ids')
                            ->label('Méretek')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function () use ($existingPrintSizeIds) {
                                return PrintSize::query()
                                    ->whereNotIn('id', $existingPrintSizeIds)
                                    ->orderBy('name')
                                    ->pluck('name', 'id');
                            })
                            ->helperText('Válaszd ki azokat a méreteket, amelyekhez árakat szeretnél létrehozni.')
                            ->visible(fn (callable $get) => ! $get('add_all'))
                            ->required(fn (callable $get) => ! $get('add_all')),
                    ]);
                })
                ->action(function (array $data) {
                    $existingPrintSizeIds = $this->record->prices()
                        ->pluck('print_size_id')
                        ->toArray();

                    $printSizeIdsToAdd = [];

                    if ($data['add_all'] ?? false) {
                        // Add all sizes that don't exist yet
                        $printSizeIdsToAdd = PrintSize::query()
                            ->whereNotIn('id', $existingPrintSizeIds)
                            ->pluck('id')
                            ->toArray();
                    } else {
                        // Add selected sizes
                        $printSizeIdsToAdd = $data['print_size_ids'] ?? [];
                    }

                    if (empty($printSizeIdsToAdd)) {
                        Notification::make()
                            ->warning()
                            ->title('Nincs mit hozzáadni')
                            ->body('Nincs kiválasztva egyetlen méret sem, vagy már az összes méret hozzá van adva.')
                            ->send();

                        return;
                    }

                    // Filter out any sizes that already exist (safety check)
                    $validPrintSizeIds = array_diff($printSizeIdsToAdd, $existingPrintSizeIds);
                    $skippedCount = count($printSizeIdsToAdd) - count($validPrintSizeIds);

                    if (empty($validPrintSizeIds)) {
                        Notification::make()
                            ->warning()
                            ->title('Méretek már léteznek')
                            ->body('A kiválasztott méretek már szerepelnek az árlistában.')
                            ->send();

                        return;
                    }

                    // Create prices with default 0 value
                    $prices = [];
                    foreach ($validPrintSizeIds as $printSizeId) {
                        $prices[] = [
                            'price_list_id' => $this->record->id,
                            'print_size_id' => $printSizeId,
                            'price' => 0,
                            'volume_discounts' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    Price::insert($prices);

                    $addedCount = count($validPrintSizeIds);
                    $message = $addedCount.' méret sikeresen hozzáadva 0 Ft alapárral.';

                    if ($skippedCount > 0) {
                        $message .= ' '.$skippedCount.' méret ki lett hagyva, mert már létezik.';
                    }

                    Notification::make()
                        ->success()
                        ->title('Méretek hozzáadva')
                        ->body($message)
                        ->send();

                    // Redirect to refresh the page and relation manager
                    return redirect()->to(static::getUrl(['record' => $this->record]));
                }),

            DeleteAction::make(),
        ];
    }
}

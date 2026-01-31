<?php

namespace App\Filament\Resources\Packages\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Csomag tételek';

    protected static ?string $label = 'Tétel';

    protected static ?string $pluralLabel = 'Tételek';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('print_size_id')
                    ->label('Papírtípus')
                    ->options(fn () => \App\Models\PrintSize::pluck('name', 'id')->toArray())
                    ->required()
                    ->searchable()
                    ->preload(),

                TextInput::make('quantity')
                    ->label('Darabszám')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required()
                    ->suffix('db'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                SelectColumn::make('print_size_id')
                    ->label('Papírtípus')
                    ->options(fn () => \App\Models\PrintSize::pluck('name', 'id')->toArray())
                    ->sortable()
                    ->selectablePlaceholder(false),

                TextInputColumn::make('quantity')
                    ->label('Darabszám')
                    ->type('number')
                    ->rules(['required', 'integer', 'min:1'])
                    ->sortable(),
            ])
            ->searchable(false)
            ->paginated(false)
            ->headerActions([
                CreateAction::make()
                    ->form([
                        Repeater::make('items')
                            ->label('Tételek')
                            ->schema([
                                Select::make('print_size_id')
                                    ->label('Papírtípus')
                                    ->options(fn () => \App\Models\PrintSize::pluck('name', 'id')->toArray())
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(1),

                                TextInput::make('quantity')
                                    ->label('Darabszám')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required()
                                    ->suffix('db')
                                    ->columnSpan(1),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->addActionLabel('Újabb tétel hozzáadása')
                            ->reorderable(false)
                            ->itemLabel(fn (array $state): ?string => \App\Models\PrintSize::find($state['print_size_id'] ?? null)?->name ?? 'Új tétel'
                            ),
                    ])
                    ->using(function (array $data, $livewire): void {
                        $package = $livewire->getOwnerRecord();

                        foreach ($data['items'] as $item) {
                            $package->items()->create([
                                'print_size_id' => $item['print_size_id'],
                                'quantity' => $item['quantity'],
                            ]);
                        }
                    })
                    ->successNotificationTitle('Tételek hozzáadva'),
            ])
            ->actions([
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('print_size_id');
    }
}

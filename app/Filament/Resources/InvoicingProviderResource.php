<?php

namespace App\Filament\Resources;

use App\Enums\InvoicingProviderType;
use App\Filament\Resources\InvoicingProviderResource\Pages;
use App\Models\InvoicingProvider;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class InvoicingProviderResource extends BaseResource
{

    protected static function getPermissionKey(): string
    {
        return 'invoicing-providers';
    }
    protected static ?string $model = InvoicingProvider::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Számlázás';

    protected static ?string $modelLabel = 'Számlázó rendszer';

    protected static ?string $pluralModelLabel = 'Számlázó rendszerek';

    protected static ?int $navigationSort = 11;

    public static function getNavigationGroup(): ?string
    {
        return 'Beállítások';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Alapadatok')
                    ->columns(2)
                    ->components([
                        Forms\Components\Select::make('provider_type')
                            ->label('Szolgáltató')
                            ->options([
                                InvoicingProviderType::SzamlazzHu->value => 'Számlázz.hu',
                                InvoicingProviderType::Billingo->value => 'Billingo',
                            ])
                            ->required()
                            ->live()
                            ->native(false),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktív')
                            ->helperText('Csak egy számlázó rendszer lehet aktív egyszerre')
                            ->default(false),
                    ]),

                Section::make('Számlázz.hu beállítások')
                    ->columns(2)
                    ->components([
                        Forms\Components\TextInput::make('api_key')
                            ->label('API Kulcs')
                            ->password()
                            ->revealable()
                            ->required()
                            ->helperText('Számlázz.hu Agent kulcs'),

                        Forms\Components\TextInput::make('agent_key')
                            ->label('Agent Kulcs')
                            ->password()
                            ->revealable()
                            ->helperText('Számlázz.hu Agent Key (opcionális)'),
                    ])
                    ->visible(fn ($get) => $get('provider_type') === InvoicingProviderType::SzamlazzHu->value),

                Section::make('Billingo beállítások')
                    ->columns(2)
                    ->components([
                        Forms\Components\TextInput::make('api_v3_key')
                            ->label('API v3 Kulcs')
                            ->password()
                            ->revealable()
                            ->required()
                            ->helperText('Billingo API v3 kulcs'),
                    ])
                    ->visible(fn ($get) => $get('provider_type') === InvoicingProviderType::Billingo->value),

                Section::make('Egyéb beállítások')
                    ->collapsible()
                    ->collapsed()
                    ->components([
                        Forms\Components\Textarea::make('settings')
                            ->label('JSON beállítások')
                            ->helperText('Egyéb provider-specifikus beállítások JSON formátumban')
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('provider_type')
                    ->label('Szolgáltató')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        InvoicingProviderType::SzamlazzHu => 'success',
                        InvoicingProviderType::Billingo => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state->label()),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktív')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Frissítve')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('provider_type')
                    ->label('Szolgáltató')
                    ->options([
                        InvoicingProviderType::SzamlazzHu->value => 'Számlázz.hu',
                        InvoicingProviderType::Billingo->value => 'Billingo',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktív'),
            ])
            ->actions([
                EditAction::make()
                    ->label('Szerkesztés'),

                Action::make('toggle_active')
                    ->label('Aktiválás')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (InvoicingProvider $record) => ! $record->is_active)
                    ->requiresConfirmation()
                    ->modalHeading('Számlázó rendszer aktiválása')
                    ->modalDescription('Biztosan aktiválni szeretnéd ezt a számlázó rendszert? A többi rendszer deaktiválódik.')
                    ->action(function (InvoicingProvider $record) {
                        InvoicingProvider::query()->update(['is_active' => false]);
                        $record->update(['is_active' => true]);
                    }),

                DeleteAction::make()
                    ->label('Törlés'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoicingProviders::route('/'),
            'create' => Pages\CreateInvoicingProvider::route('/create'),
            'edit' => Pages\EditInvoicingProvider::route('/{record}/edit'),
        ];
    }
}

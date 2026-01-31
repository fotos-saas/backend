<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Services\InvoicingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends BaseResource
{

    protected static ?string $model = Order::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $navigationLabel = 'Rendelések';

    protected static ?string $modelLabel = 'Rendelés';

    protected static ?string $pluralModelLabel = 'Rendelések';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rendelés adatok')
                    ->columns(2)
                    ->components([
                        Forms\Components\Select::make('user_id')
                            ->label('Felhasználó')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Select::make('work_session_id')
                            ->label('Work Session')
                            ->relationship('workSession', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Select::make('package_id')
                            ->label('Csomag')
                            ->relationship('package', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Select::make('coupon_id')
                            ->label('Kupon')
                            ->relationship('coupon', 'code')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Select::make('status')
                            ->label('Státusz')
                            ->options([
                                'pending' => 'Függőben',
                                'paid' => 'Fizetve',
                                'processing' => 'Feldolgozás alatt',
                                'shipped' => 'Kiszállítva',
                                'completed' => 'Teljesítve',
                                'cancelled' => 'Törölve',
                                'refunded' => 'Visszatérítve',
                            ])
                            ->required()
                            ->default('pending'),

                        Forms\Components\TextInput::make('stripe_pi')
                            ->label('Stripe Payment Intent')
                            ->maxLength(255)
                            ->disabled(),

                        Forms\Components\TextInput::make('invoice_no')
                            ->label('Számla száma')
                            ->maxLength(255),
                    ]),

                Section::make('Vendég adatok')
                    ->description('Csak vendég vásárlásoknál töltődik ki')
                    ->columns(2)
                    ->components([
                        Forms\Components\TextInput::make('guest_name')
                            ->label('Név')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('guest_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('guest_phone')
                            ->label('Telefonszám')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\Textarea::make('guest_address')
                            ->label('Cím')
                            ->columnSpanFull(),
                    ]),

                Section::make('Számlázási adatok')
                    ->description('Céges vásárlásnál kitöltött adatok')
                    ->columns(2)
                    ->components([
                        Forms\Components\Toggle::make('is_company_purchase')
                            ->label('Céges vásárlás')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('company_name')
                            ->label('Cégnév')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('tax_number')
                            ->label('Adószám')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('billing_address')
                            ->label('Számlázási cím')
                            ->columnSpanFull(),
                    ]),

                Section::make('Összegek')
                    ->columns(2)
                    ->components([
                        Forms\Components\TextInput::make('subtotal_huf')
                            ->label('Részösszeg (Ft)')
                            ->numeric()
                            ->suffix('Ft')
                            ->mask(RawJs::make('$money($input, \' \', \'.\', 0)'))
                            ->required(),

                        Forms\Components\TextInput::make('discount_huf')
                            ->label('Kedvezmény (Ft)')
                            ->numeric()
                            ->suffix('Ft')
                            ->mask(RawJs::make('$money($input, \' \', \'.\', 0)'))
                            ->default(0)
                            ->required(),

                        Forms\Components\TextInput::make('coupon_discount')
                            ->label('Kupon kedvezmény (Ft)')
                            ->numeric()
                            ->suffix('Ft')
                            ->mask(RawJs::make('$money($input, \' \', \'.\', 0)'))
                            ->nullable(),

                        Forms\Components\TextInput::make('total_gross_huf')
                            ->label('Végösszeg (Ft)')
                            ->numeric()
                            ->suffix('Ft')
                            ->mask(RawJs::make('$money($input, \' \', \'.\', 0)'))
                            ->required(),
                    ]),

                Section::make('Tételek')
                    ->components([
                        Forms\Components\Placeholder::make('items_count')
                            ->label('Tételek száma')
                            ->content(fn ($record) => $record ? $record->items()->count() : 0),

                        Forms\Components\Placeholder::make('created_at')
                            ->label('Létrehozva')
                            ->content(fn ($record) => $record ? $record->created_at->format('Y-m-d H:i') : '-'),
                    ])
                    ->columns(2)
                    ->hidden(fn ($record) => $record === null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Felhasználó')
                    ->searchable()
                    ->sortable()
                    ->default('-')
                    ->getStateUsing(fn ($record) => $record->user?->name ?? $record->guest_name ?? 'Vendég'),

                Tables\Columns\TextColumn::make('guest_email')
                    ->label('Email')
                    ->searchable()
                    ->getStateUsing(fn ($record) => $record->user?->email ?? $record->guest_email ?? '-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('guest_phone')
                    ->label('Telefon')
                    ->getStateUsing(fn ($record) => $record->user?->phone ?? $record->guest_phone ?? '-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Státusz')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'pending' => 'warning',
                        'paid', 'processing', 'shipped', 'completed' => 'success',
                        'cancelled', 'refunded' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending' => 'Függőben',
                        'paid' => 'Fizetve',
                        'processing' => 'Feldolgozás alatt',
                        'shipped' => 'Kiszállítva',
                        'completed' => 'Teljesítve',
                        'cancelled' => 'Törölve',
                        'refunded' => 'Visszatérítve',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Tételek')
                    ->counts('items')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('total_gross_huf')
                    ->label('Összeg')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', ' ').' Ft')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\IconColumn::make('invoice_no')
                    ->label('Számla')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->getStateUsing(fn ($record) => ! empty($record->invoice_no)),

                Tables\Columns\TextColumn::make('invoice_no')
                    ->label('Számlaszám')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dátum')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Státusz')
                    ->options([
                        'draft' => 'Piszkozat',
                        'payment_pending' => 'Fizetésre vár',
                        'paid' => 'Fizetve',
                        'in_production' => 'Gyártás alatt',
                        'delivered' => 'Kézbesítve',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Ettől'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Eddig'),
                    ]),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Megtekintés'),
                    EditAction::make()
                        ->label('Szerkesztés'),
                    Action::make('issue_invoice')
                        ->label('Számla kiállítása')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Számla kiállítása')
                        ->modalDescription('Biztosan ki szeretnéd állítani a számlát ehhez a rendeléshez?')
                        ->visible(fn (Order $record) => $record->isPaid() && ! $record->invoice_no)
                        ->action(function (Order $record) {
                            try {
                                app(InvoicingService::class)->issueInvoiceForOrder($record);
                                Notification::make()
                                    ->title('Számla sikeresen kiállítva!')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Hiba a számlakiállításnál')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('download_invoice')
                        ->label('Számla letöltése')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('info')
                        ->visible(fn (Order $record) => ! empty($record->invoice_no))
                        ->url(fn (Order $record) => route('api.orders.invoice.download', $record), shouldOpenInNewTab: true),
                    ReplicateAction::make()
                        ->label('Másolás'),
                    DeleteAction::make()
                        ->label('Törlés')
                        ->requiresConfirmation()
                        ->modalHeading('Rendelés törlése')
                        ->modalDescription('Biztosan törölni szeretnéd ezt a rendelést? Ez a művelet nem vonható vissza!')
                        ->modalSubmitActionLabel('Igen, törlöm'),
                ])
                    ->label('Műveletek')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\OrderResource\RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}

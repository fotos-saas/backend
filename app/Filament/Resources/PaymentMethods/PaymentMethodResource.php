<?php

namespace App\Filament\Resources\PaymentMethods;

use App\Filament\Resources\BaseResource;

use App\Filament\Resources\PaymentMethods\Pages\EditPaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\ListPaymentMethods;
use App\Filament\Resources\PaymentMethods\Schemas\PaymentMethodForm;
use App\Filament\Resources\PaymentMethods\Tables\PaymentMethodsTable;
use App\Models\PaymentMethod;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PaymentMethodResource extends BaseResource
{

    protected static function getPermissionKey(): string
    {
        return 'payment-methods';
    }
    protected static ?string $model = PaymentMethod::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Fizetési módok';

    protected static ?string $modelLabel = 'Fizetési mód';

    protected static ?string $pluralModelLabel = 'Fizetési módok';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Szállítás és Fizetés';
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentMethodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentMethodsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentMethods::route('/'),
            'edit' => EditPaymentMethod::route('/{record}/edit'),
        ];
    }
}

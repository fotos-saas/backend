<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceType: string
{
    case INVOICE = 'invoice';
    case PROFORMA = 'proforma';
    case DEPOSIT = 'deposit';
    case CANCELLATION = 'cancellation';

    public function label(): string
    {
        return match ($this) {
            self::INVOICE => 'Számla',
            self::PROFORMA => 'Díjbekérő',
            self::DEPOSIT => 'Előlegszámla',
            self::CANCELLATION => 'Sztornó számla',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

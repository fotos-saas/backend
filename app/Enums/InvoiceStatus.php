<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case OVERDUE = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Piszkozat',
            self::SENT => 'Kiküldve',
            self::PAID => 'Fizetve',
            self::CANCELLED => 'Sztornózva',
            self::OVERDUE => 'Lejárt',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SENT => 'blue',
            self::PAID => 'green',
            self::CANCELLED => 'red',
            self::OVERDUE => 'orange',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

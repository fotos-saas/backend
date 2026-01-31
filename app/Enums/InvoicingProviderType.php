<?php

namespace App\Enums;

enum InvoicingProviderType: string
{
    case SzamlazzHu = 'szamlazz_hu';
    case Billingo = 'billingo';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::SzamlazzHu => 'Számlázz.hu',
            self::Billingo => 'Billingo',
        };
    }

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

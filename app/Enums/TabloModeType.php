<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TabloModeType: string implements HasLabel
{
    case FIXED = 'fixed';
    case FLEXIBLE = 'flexible';
    case PACKAGES = 'packages';

    /**
     * Magyar címke lekérdezése.
     */
    public function getLabel(): ?string
    {
        return match ($this) {
            self::FIXED => 'Fix számú retusálás',
            self::FLEXIBLE => 'Rugalmas limit',
            self::PACKAGES => 'Csomag alapú',
        };
    }

    /**
     * Magyar leírás lekérdezése.
     */
    public function description(): string
    {
        return match ($this) {
            self::FIXED => 'Minden felhasználó pontosan X képet retusáltathat',
            self::FLEXIBLE => 'Maximum X kép ingyen, továbbiakért egyéni ár',
            self::PACKAGES => 'Felhasználók előre definiált csomagok közül választhatnak',
        };
    }

    /**
     * Heroicon ikon lekérdezése.
     */
    public function icon(): string
    {
        return match ($this) {
            self::FIXED => 'heroicon-o-lock-closed',
            self::FLEXIBLE => 'heroicon-o-adjustments-horizontal',
            self::PACKAGES => 'heroicon-o-cube',
        };
    }

    /**
     * Összes enum érték lekérdezése tömbként.
     */
    public static function toArray(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Összes enum label lekérdezése tömbként (Filament select opciókhoz).
     */
    public static function labels(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $case) => $case->getLabel(), self::cases())
        );
    }
}

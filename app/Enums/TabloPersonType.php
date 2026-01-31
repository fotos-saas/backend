<?php

namespace App\Enums;

/**
 * Tabló hiányzó személy típusok
 */
enum TabloPersonType: string
{
    case TEACHER = 'teacher';
    case STUDENT = 'student';

    /**
     * Egyes számú magyar címke
     */
    public function label(): string
    {
        return match ($this) {
            self::TEACHER => 'Tanár',
            self::STUDENT => 'Diák',
        };
    }

    /**
     * Többes számú magyar címke
     */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::TEACHER => 'Tanárok',
            self::STUDENT => 'Diákok',
        };
    }

    /**
     * Heroicon ikon neve
     */
    public function icon(): string
    {
        return match ($this) {
            self::TEACHER => 'heroicon-o-academic-cap',
            self::STUDENT => 'heroicon-o-user-group',
        };
    }

    /**
     * Filament badge szín
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::TEACHER => 'primary',
            self::STUDENT => 'success',
        };
    }

    /**
     * Warning badge szín (használható más kontextusban)
     */
    public function warningBadgeColor(): string
    {
        return match ($this) {
            self::TEACHER => 'warning',
            self::STUDENT => 'primary',
        };
    }
}

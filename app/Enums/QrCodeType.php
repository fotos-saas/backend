<?php

namespace App\Enums;

enum QrCodeType: string
{
    case Coordinator = 'coordinator';
    case ParentType = 'parent';
    case Student = 'student';
    case Teacher = 'teacher';
    case Management = 'management';
    case Visitor = 'visitor';

    /**
     * Magyar címke
     */
    public function label(): string
    {
        return match ($this) {
            self::Coordinator => 'Kapcsolattartó',
            self::ParentType => 'Szülő',
            self::Student => 'Diák',
            self::Teacher => 'Osztályfőnök',
            self::Management => 'Igazgatóság',
            self::Visitor => 'Látogató',
        };
    }

    /**
     * A regisztrált felhasználó kapcsolattartó lesz-e
     */
    public function isCoordinator(): bool
    {
        return $this === self::Coordinator;
    }

    /**
     * A regisztrált felhasználó extra tag lesz-e (nem számít bele a létszámba)
     */
    public function isExtra(): bool
    {
        return match ($this) {
            self::Coordinator, self::Student => false,
            self::ParentType, self::Teacher, self::Management, self::Visitor => true,
        };
    }

    /**
     * Létrehozzon-e TabloContact-ot regisztrációkor
     */
    public function shouldCreateContact(): bool
    {
        return match ($this) {
            self::Coordinator, self::Teacher => true,
            self::ParentType, self::Student, self::Management, self::Visitor => false,
        };
    }

    /**
     * Megjegyzés a kontakthoz
     */
    public function contactNote(): string
    {
        return match ($this) {
            self::Coordinator => 'QR kóddal regisztrált kapcsolattartó',
            self::Teacher => 'QR kóddal regisztrált osztályfőnök',
            default => '',
        };
    }

    /**
     * Multi-use típus-e (többször használható)
     */
    public function isMultiUse(): bool
    {
        return $this !== self::Coordinator;
    }
}

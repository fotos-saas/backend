<?php

namespace App\Enums;

enum NoteStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Done = 'done';
    case NotRelevant = 'not_relevant';

    /**
     * Get human-readable label (Hungarian)
     */
    public function label(): string
    {
        return match ($this) {
            self::New => 'Új',
            self::InProgress => 'Folyamatban',
            self::Done => 'Elintézve',
            self::NotRelevant => 'Nem releváns',
        };
    }

    /**
     * Get color for badge
     */
    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::InProgress => 'info',
            self::Done => 'success',
            self::NotRelevant => 'gray',
        };
    }

    /**
     * Get icon for status
     */
    public function icon(): string
    {
        return match ($this) {
            self::New => 'heroicon-o-bell-alert',
            self::InProgress => 'heroicon-o-arrow-path',
            self::Done => 'heroicon-o-check-circle',
            self::NotRelevant => 'heroicon-o-x-circle',
        };
    }

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get options for select (value => label)
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}

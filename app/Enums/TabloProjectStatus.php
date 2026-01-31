<?php

namespace App\Enums;

enum TabloProjectStatus: string
{
    case NotStarted = 'not_started';                    // 1
    case ShouldFinish = 'should_finish';                // 2
    case WaitingForResponse = 'waiting_for_response';   // 3
    case Done = 'done';                                 // 4
    case WaitingForFinalization = 'waiting_for_finalization'; // 5
    case InPrint = 'in_print';                          // 6
    case WaitingForPhotos = 'waiting_for_photos';       // 7
    case GotResponse = 'got_response';                  // 8
    case NeedsForwarding = 'needs_forwarding';          // 9
    case AtTeacherForFinalization = 'at_teacher_for_finalization'; // 10
    case NeedsCall = 'needs_call';                      // 11
    case SosWaitingForPhotos = 'sos_waiting_for_photos'; // 12
    case PushCouldBeDone = 'push_could_be_done';        // 13

    /**
     * Get human-readable label (Hungarian)
     */
    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Nincs elkezdve',
            self::ShouldFinish => 'Be kellene fejeznem',
            self::WaitingForResponse => 'Válaszra várok',
            self::Done => 'Kész',
            self::WaitingForFinalization => 'Véglegesítésre várok',
            self::InPrint => 'Nyomdában',
            self::WaitingForPhotos => 'Képekre várok',
            self::GotResponse => 'Kaptam választ',
            self::NeedsForwarding => 'Tovább kell küldeni',
            self::AtTeacherForFinalization => 'Osztályfőnöknél véglegesítésen',
            self::NeedsCall => 'Fel kell hívni, mert nem válaszol',
            self::SosWaitingForPhotos => 'SOS képekre vár',
            self::PushCouldBeDone => 'Nyomni, mert kész lehetne',
        };
    }

    /**
     * Get color for badge
     */
    public function color(): string
    {
        return match ($this) {
            self::NotStarted => 'gray',
            self::ShouldFinish => 'warning',
            self::WaitingForResponse => 'info',
            self::Done => 'success',
            self::WaitingForFinalization => 'info',
            self::InPrint => 'purple',
            self::WaitingForPhotos => 'danger',
            self::GotResponse => 'success',
            self::NeedsForwarding => 'warning',
            self::AtTeacherForFinalization => 'info',
            self::NeedsCall => 'danger',
            self::SosWaitingForPhotos => 'danger',
            self::PushCouldBeDone => 'warning',
        };
    }

    /**
     * Get sort order for grouping (lower = higher priority)
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::NeedsForwarding => 1,              // Tovább kell küldeni
            self::GotResponse => 2,                  // Kaptam választ
            self::NeedsCall => 3,                    // Fel kell hívni
            self::WaitingForResponse => 4,           // Válaszra várok
            self::WaitingForFinalization => 5,       // Véglegesítésre várok
            self::AtTeacherForFinalization => 6,     // Osztályfőnöknél véglegesítésen
            self::NotStarted => 7,                   // Nincs elkezdve
            self::SosWaitingForPhotos => 8,          // SOS képekre vár
            self::WaitingForPhotos => 9,             // Képekre várok
            self::PushCouldBeDone => 10,             // Nyomni, mert kész lehetne
            self::ShouldFinish => 11,                // Be kellene fejeznem
            self::InPrint => 12,                     // Nyomdában
            self::Done => 13,                        // Kész
        };
    }

    /**
     * Get legacy ID (for API compatibility)
     */
    public function legacyId(): int
    {
        return match ($this) {
            self::NotStarted => 1,
            self::ShouldFinish => 2,
            self::WaitingForResponse => 3,
            self::Done => 4,
            self::WaitingForFinalization => 5,
            self::InPrint => 6,
            self::WaitingForPhotos => 7,
            self::GotResponse => 8,
            self::NeedsForwarding => 9,
            self::AtTeacherForFinalization => 10,
            self::NeedsCall => 11,
            self::SosWaitingForPhotos => 12,
            self::PushCouldBeDone => 13,
        };
    }

    /**
     * Create from legacy ID
     */
    public static function fromLegacyId(int $id): ?self
    {
        return match ($id) {
            1 => self::NotStarted,
            2 => self::ShouldFinish,
            3 => self::WaitingForResponse,
            4 => self::Done,
            5 => self::WaitingForFinalization,
            6 => self::InPrint,
            7 => self::WaitingForPhotos,
            8 => self::GotResponse,
            9 => self::NeedsForwarding,
            10 => self::AtTeacherForFinalization,
            11 => self::NeedsCall,
            12 => self::SosWaitingForPhotos,
            13 => self::PushCouldBeDone,
            default => null,
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

    /**
     * Get Tailwind color name for frontend badges
     */
    public function tailwindColor(): string
    {
        return match ($this) {
            self::NotStarted => 'gray',
            self::ShouldFinish => 'amber',
            self::WaitingForResponse => 'blue',
            self::Done => 'green',
            self::WaitingForFinalization => 'blue',
            self::InPrint => 'purple',
            self::WaitingForPhotos => 'red',
            self::GotResponse => 'green',
            self::NeedsForwarding => 'amber',
            self::AtTeacherForFinalization => 'blue',
            self::NeedsCall => 'red',
            self::SosWaitingForPhotos => 'red',
            self::PushCouldBeDone => 'amber',
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TabloPokeDailyLimit - Napi bökés limit követése.
 *
 * @property int $id
 * @property int $from_guest_session_id
 * @property \Carbon\Carbon $date
 * @property int $pokes_sent
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TabloPokeDailyLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_guest_session_id',
        'date',
        'pokes_sent',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'pokes_sent' => 'integer',
        ];
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Session
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TabloGuestSession::class, 'from_guest_session_id');
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    /**
     * Mai limit lekérése/létrehozása
     */
    public static function getOrCreateForToday(int $sessionId): self
    {
        return static::firstOrCreate(
            [
                'from_guest_session_id' => $sessionId,
                'date' => today(),
            ],
            ['pokes_sent' => 0]
        );
    }

    /**
     * Mai küldések száma
     */
    public static function getTodayCount(int $sessionId): int
    {
        $record = static::where('from_guest_session_id', $sessionId)
            ->where('date', today())
            ->first();

        return $record?->pokes_sent ?? 0;
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Küldés inkrementálása
     */
    public function incrementSent(): void
    {
        $this->increment('pokes_sent');
    }

    /**
     * Elérte-e a napi limitet
     */
    public function hasReachedLimit(): bool
    {
        return $this->pokes_sent >= TabloPoke::DAILY_LIMIT;
    }

    /**
     * Hátralévő bökések száma
     */
    public function getRemainingPokes(): int
    {
        return max(0, TabloPoke::DAILY_LIMIT - $this->pokes_sent);
    }
}

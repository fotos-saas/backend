<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestBillingCharge extends Model
{
    public const SERVICE_TYPES = [
        'photo_change',
        'extra_retouch',
        'late_fee',
        'rush_fee',
        'additional_copy',
        'custom',
    ];

    public const SERVICE_LABELS = [
        'photo_change' => 'Képcsere',
        'extra_retouch' => 'Extra retusálás',
        'late_fee' => 'Késedelmi díj',
        'rush_fee' => 'Sürgősségi díj',
        'additional_copy' => 'Plusz példány',
        'custom' => 'Egyedi',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'tablo_project_id',
        'tablo_guest_session_id',
        'tablo_person_id',
        'charge_number',
        'service_type',
        'description',
        'amount_huf',
        'status',
        'due_date',
        'paid_at',
        'stripe_payment_intent_id',
        'stripe_checkout_session_id',
        'invoice_number',
        'invoice_url',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_huf' => 'integer',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    // ============ Relációk ============

    public function project(): BelongsTo
    {
        return $this->belongsTo(TabloProject::class, 'tablo_project_id');
    }

    public function guestSession(): BelongsTo
    {
        return $this->belongsTo(TabloGuestSession::class, 'tablo_guest_session_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(TabloPerson::class, 'tablo_person_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ============ Scopes ============

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('tablo_project_id', $projectId);
    }

    public function scopeForPerson($query, int $personId)
    {
        return $query->where('tablo_person_id', $personId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    // ============ Helpers ============

    public function getServiceLabelAttribute(): string
    {
        return self::SERVICE_LABELS[$this->service_type] ?? $this->service_type;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public static function generateChargeNumber(): string
    {
        $date = now()->format('Ymd');
        $lastCharge = static::where('charge_number', 'like', "T{$date}-%")
            ->orderByDesc('charge_number')
            ->first();

        if ($lastCharge) {
            $lastNumber = (int) substr($lastCharge->charge_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('T%s-%04d', $date, $nextNumber);
    }
}

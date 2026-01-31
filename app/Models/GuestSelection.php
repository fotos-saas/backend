<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestSelection extends Model
{
    protected $fillable = [
        'guest_token_id',
        'photo_id',
        'selected',
        'quantity',
        'notes',
    ];

    protected $casts = [
        'selected' => 'boolean',
        'quantity' => 'integer',
    ];

    /**
     * Guest share token relationship
     */
    public function guestToken(): BelongsTo
    {
        return $this->belongsTo(GuestShareToken::class, 'guest_token_id');
    }

    /**
     * Photo relationship
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }
}

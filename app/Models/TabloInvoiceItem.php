<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabloInvoiceItem extends Model
{
    protected $fillable = [
        'tablo_invoice_id',
        'guest_billing_charge_id',
        'name',
        'description',
        'unit',
        'quantity',
        'unit_price',
        'net_amount',
        'vat_percentage',
        'vat_amount',
        'gross_amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'vat_percentage' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TabloInvoice::class, 'tablo_invoice_id');
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(GuestBillingCharge::class, 'guest_billing_charge_id');
    }
}

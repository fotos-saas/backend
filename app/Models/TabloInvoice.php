<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\InvoicingProviderType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TabloInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tablo_partner_id',
        'tablo_project_id',
        'tablo_contact_id',
        'provider',
        'external_id',
        'invoice_number',
        'type',
        'status',
        'issue_date',
        'due_date',
        'fulfillment_date',
        'paid_at',
        'currency',
        'net_amount',
        'vat_amount',
        'gross_amount',
        'vat_percentage',
        'customer_name',
        'customer_email',
        'customer_tax_number',
        'customer_address',
        'pdf_path',
        'comment',
        'internal_note',
        'synced_at',
        'provider_metadata',
    ];

    protected $casts = [
        'provider' => InvoicingProviderType::class,
        'type' => InvoiceType::class,
        'status' => InvoiceStatus::class,
        'issue_date' => 'date',
        'due_date' => 'date',
        'fulfillment_date' => 'date',
        'paid_at' => 'datetime',
        'synced_at' => 'datetime',
        'net_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'vat_percentage' => 'decimal:2',
        'provider_metadata' => 'array',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(TabloPartner::class, 'tablo_partner_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(TabloProject::class, 'tablo_project_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(TabloContact::class, 'tablo_contact_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TabloInvoiceItem::class, 'tablo_invoice_id');
    }

    public function isOverdue(): bool
    {
        return $this->status !== InvoiceStatus::PAID
            && $this->status !== InvoiceStatus::CANCELLED
            && $this->due_date->isPast();
    }

    public function markAsPaid(): void
    {
        $this->update([
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);
    }

    public function getFormattedAmount(): string
    {
        $amount = number_format((float) $this->gross_amount, 0, ',', ' ');

        return "{$amount} {$this->currency}";
    }
}

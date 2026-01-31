<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerSetting extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slogan',
        'logo',
        'favicon',
        'brand_color',
        'email',
        'phone',
        'address',
        'tax_number',
        'website',
        'landing_page_url',
        'instagram_url',
        'facebook_url',
        'is_active',
        'stripe_secret_key',
        'stripe_public_key',
        'stripe_webhook_secret',
        'allow_company_invoicing',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'allow_company_invoicing' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $setting): void {
            if ($setting->is_active) {
                static::query()
                    ->whereKeyNot($setting->getKey())
                    ->update(['is_active' => false]);
            }
        });
    }
}

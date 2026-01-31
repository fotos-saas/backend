<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_name',
        'customer_title',
        'customer_email',
        'customer_phone',
        'quote_date',
        'quote_number',
        'quote_type',
        'quote_category',
        'size',
        'intro_text',
        'content_items',
        'price_list_items',
        'volume_discounts',
        'is_full_execution',
        'has_small_tablo',
        'has_shipping',
        'has_production',
        'base_price',
        'discount_price',
        'small_tablo_price',
        'shipping_price',
        'production_price',
        'small_tablo_text',
        'production_text',
        'discount_text',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quote_date' => 'date',
            'content_items' => AsArrayObject::class,
            'price_list_items' => AsArrayObject::class,
            'volume_discounts' => AsArrayObject::class,
            'is_full_execution' => 'boolean',
            'has_small_tablo' => 'boolean',
            'has_shipping' => 'boolean',
            'has_production' => 'boolean',
            'base_price' => 'integer',
            'discount_price' => 'integer',
            'small_tablo_price' => 'integer',
            'shipping_price' => 'integer',
            'production_price' => 'integer',
        ];
    }

    /**
     * Elérhető placeholder változók
     */
    public static function getAvailableVariables(): array
    {
        return [
            '{{customer_name}}' => 'Ügyfél neve',
            '{{customer_title}}' => 'Megszólítás',
            '{{size}}' => 'Méret',
            '{{date}}' => 'Árajánlat dátuma',
            '{{quote_number}}' => 'Árajánlat száma',
            '{{base_price}}' => 'Alap ár (Ft)',
            '{{discount_price}}' => 'Kedvezményes ár (Ft)',
            '{{small_tablo_price}}' => 'Kistabló ár (Ft)',
            '{{shipping_price}}' => 'Szállítási díj (Ft)',
            '{{total_price}}' => 'Teljes ár (Ft)',
        ];
    }

    /**
     * Placeholder-ek cseréje valós adatokra
     */
    public function replacePlaceholders(string $text): string
    {
        $totalPrice = $this->calculateTotalPrice();

        $replacements = [
            '{{customer_name}}' => $this->customer_name ?? '',
            '{{customer_title}}' => $this->customer_title ?? '',
            '{{size}}' => $this->size ?? '',
            '{{date}}' => $this->quote_date ? $this->quote_date->format('Y. m. d.') : '',
            '{{quote_number}}' => $this->quote_number ?? '',
            '{{base_price}}' => $this->formatPrice($this->base_price),
            '{{discount_price}}' => $this->formatPrice($this->discount_price),
            '{{small_tablo_price}}' => $this->formatPrice($this->small_tablo_price),
            '{{shipping_price}}' => $this->formatPrice($this->shipping_price),
            '{{total_price}}' => $this->formatPrice($totalPrice),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Ár formázása Ft-ban
     */
    public function formatPrice(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' Ft';
    }

    /**
     * Teljes ár kalkuláció
     */
    public function calculateTotalPrice(): int
    {
        $total = $this->discount_price > 0 ? $this->discount_price : $this->base_price;

        if ($this->has_small_tablo) {
            $total += $this->small_tablo_price;
        }

        if ($this->has_shipping) {
            $total += $this->shipping_price;
        }

        if ($this->has_production) {
            $total += $this->production_price;
        }

        return $total;
    }

    /**
     * Automatikus quote number generálás Base32 hash-sel
     *
     * Formátum: AJ-2026-K7W3N9
     * - Egyedi és követhetetlen (nem számláló alapú)
     * - Ember által jól olvasható (nincs O/0, I/1 keveredés)
     * - URL-safe és fájlnév-safe
     * - 1,073,741,824 lehetséges kombináció (32^6)
     */
    public static function generateQuoteNumber(): string
    {
        $year = Carbon::now()->year;
        $prefix = 'AJ-'.$year.'-';

        // Base32 karakterkészlet (nincs O, I, hogy ne keveredjen 0-val és 1-gyel)
        $base32Chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        // Maximum 10 próbálkozás, hogy ne legyen végtelen ciklus
        $maxAttempts = 10;
        $attempt = 0;

        do {
            $hash = '';
            for ($i = 0; $i < 6; $i++) {
                $hash .= $base32Chars[random_int(0, strlen($base32Chars) - 1)];
            }

            $quoteNumber = $prefix.$hash;

            // Ellenőrizzük, hogy nem létezik-e már
            $exists = self::where('quote_number', $quoteNumber)->exists();
            $attempt++;

            if ($attempt >= $maxAttempts && $exists) {
                // Fallback: timestamp-alapú egyedi azonosító
                $quoteNumber = $prefix.strtoupper(substr(md5(microtime(true)), 0, 6));
                break;
            }
        } while ($exists);

        return $quoteNumber;
    }

    /**
     * Quote type human-readable megjelenítés
     */
    public function getQuoteTypeLabel(): string
    {
        return match ($this->quote_type) {
            'repro' => 'Repro',
            'full_production' => 'Teljes kivitelezés',
            'digital' => 'Digitális',
            default => $this->quote_type,
        };
    }

    /**
     * Fotós típusú árajánlat-e
     */
    public function isPhotographerQuote(): bool
    {
        return $this->quote_category === 'photographer';
    }

    /**
     * Egyedi típusú árajánlat-e
     */
    public function isCustomQuote(): bool
    {
        return $this->quote_category === 'custom';
    }

    /**
     * Quote kategória human-readable megjelenítés
     */
    public function getQuoteCategoryLabel(): string
    {
        return match ($this->quote_category) {
            'photographer' => 'Fotós',
            'custom' => 'Egyedi',
            default => $this->quote_category ?? 'Egyedi',
        };
    }
}

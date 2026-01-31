<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Application settings model
 *
 * Stores key-value configuration pairs for dynamic settings
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'json',
    ];

    /**
     * Get a setting value by key
     *
     * @param  string  $key  Setting key
     * @param  mixed  $default  Default value if not found
     * @return mixed Setting value or default
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = self::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value by key
     *
     * @param  string  $key  Setting key
     * @param  mixed  $value  Setting value
     */
    public static function set(string $key, mixed $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}

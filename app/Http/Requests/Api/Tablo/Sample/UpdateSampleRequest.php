<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tablo\Sample;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Minta státusz frissítése validáció
 */
class UpdateSampleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_active' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'is_active.required' => 'Az aktív státusz megadása kötelező.',
            'is_active.boolean' => 'Az aktív státusz csak igaz vagy hamis lehet.',
        ];
    }
}

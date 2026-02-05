<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class ExtendExpiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expires_at' => 'required|date|after:now',
        ];
    }

    public function messages(): array
    {
        return [
            'expires_at.required' => 'A lejárati dátum megadása kötelező.',
            'expires_at.date' => 'Érvénytelen dátum formátum.',
            'expires_at.after' => 'A lejárati dátum nem lehet a múltban.',
        ];
    }
}

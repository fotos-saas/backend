<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Cart;

use Illuminate\Foundation\Http\FormRequest;

class CalculateCartPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array',
            'items.*.photoId' => 'required|integer',
            'items.*.type' => 'required|in:print',
            'items.*.sizeId' => 'required|integer',
            'items.*.qty' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'A kosár elemek megadása kötelező.',
            'items.*.photoId.required' => 'A kép azonosító megadása kötelező minden elemnél.',
            'items.*.type.required' => 'A típus megadása kötelező minden elemnél.',
            'items.*.type.in' => 'Érvénytelen típus. Csak nyomtatás engedélyezett.',
            'items.*.sizeId.required' => 'A méret kiválasztása kötelező minden elemnél.',
            'items.*.qty.required' => 'A mennyiség megadása kötelező minden elemnél.',
            'items.*.qty.min' => 'A mennyiségnek legalább 1-nek kell lennie.',
        ];
    }
}

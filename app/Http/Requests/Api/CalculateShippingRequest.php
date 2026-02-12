<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Szállítási költség kalkuláció validáció
 */
class CalculateShippingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array',
            'items.*.size' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'A tételek megadása kötelező.',
            'items.*.size.required' => 'Minden tételhez méret megadása kötelező.',
            'items.*.quantity.required' => 'Minden tételhez darabszám megadása kötelező.',
            'items.*.quantity.min' => 'A darabszám minimum 1.',
            'payment_method_id.exists' => 'A megadott fizetési mód nem létezik.',
        ];
    }
}

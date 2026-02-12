<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Cart;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qty' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'qty.required' => 'A mennyiség megadása kötelező.',
            'qty.min' => 'A mennyiségnek legalább 1-nek kell lennie.',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Cart;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photoId' => 'required|integer|exists:photos,id',
            'printSizeId' => 'nullable|integer|exists:print_sizes,id',
            'qty' => 'required|integer|min:1',
            'workSessionId' => 'required|integer|exists:work_sessions,id',
        ];
    }

    public function messages(): array
    {
        return [
            'photoId.required' => 'A kép kiválasztása kötelező.',
            'photoId.exists' => 'A megadott kép nem található.',
            'qty.required' => 'A mennyiség megadása kötelező.',
            'qty.min' => 'A mennyiségnek legalább 1-nek kell lennie.',
            'workSessionId.required' => 'A munkamenet kiválasztása kötelező.',
            'workSessionId.exists' => 'A megadott munkamenet nem található.',
        ];
    }
}

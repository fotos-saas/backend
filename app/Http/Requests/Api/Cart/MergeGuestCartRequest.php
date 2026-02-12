<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Cart;

use Illuminate\Foundation\Http\FormRequest;

class MergeGuestCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sessionToken' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'sessionToken.required' => 'A munkamenet token megadása kötelező.',
        ];
    }
}

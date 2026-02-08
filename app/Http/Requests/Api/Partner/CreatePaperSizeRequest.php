<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaperSizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'width_cm' => ['required', 'numeric', 'min:1', 'max:200'],
            'height_cm' => ['required', 'numeric', 'min:1', 'max:200'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'A méret neve kötelező.',
            'width_cm.required' => 'A szélesség megadása kötelező.',
            'height_cm.required' => 'A magasság megadása kötelező.',
        ];
    }
}

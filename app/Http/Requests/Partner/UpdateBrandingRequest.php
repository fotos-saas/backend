<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brand_name' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'brand_name.max' => 'A márkanév maximum 100 karakter lehet.',
            'is_active.required' => 'Az aktív mező megadása kötelező.',
            'is_active.boolean' => 'Az aktív mező csak igaz/hamis lehet.',
        ];
    }
}

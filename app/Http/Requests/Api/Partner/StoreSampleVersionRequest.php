<?php

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class StoreSampleVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['image', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'images.required' => 'Legalább egy minta kép kötelező.',
            'images.min' => 'Legalább egy minta kép kötelező.',
            'images.*.image' => 'A fájl nem érvényes kép.',
            'images.*.max' => 'Egy kép maximum 10 MB lehet.',
            'images.*.mimetypes' => 'Csak JPEG, PNG és WebP formátum engedélyezett.',
            'description.required' => 'A leírás kötelező.',
            'description.max' => 'A leírás maximum 2000 karakter lehet.',
        ];
    }
}

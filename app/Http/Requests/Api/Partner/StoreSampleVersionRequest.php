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
            'image' => ['required', 'image', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp'],
            'description' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'A minta kép kötelező.',
            'image.image' => 'A fájl nem érvényes kép.',
            'image.max' => 'A kép maximum 10 MB lehet.',
            'image.mimetypes' => 'Csak JPEG, PNG és WebP formátum engedélyezett.',
            'description.required' => 'A leírás kötelező.',
            'description.max' => 'A leírás maximum 2000 karakter lehet.',
        ];
    }
}

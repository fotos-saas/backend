<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Személy fotó feltöltése validáció
 */
class UploadPersonPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => 'required|file|mimes:jpg,jpeg,png,webp|max:20480',
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'A fotó feltöltése kötelező.',
            'photo.file' => 'Érvénytelen fájl.',
            'photo.mimes' => 'Csak JPG, JPEG, PNG és WebP formátumok engedélyezettek.',
            'photo.max' => 'A kép maximum 20MB lehet.',
        ];
    }

    public function attributes(): array
    {
        return [
            'photo' => 'fotó',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Album feltöltés validáció
 */
class UploadToAlbumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photos' => 'required_without:zip|array|max:50',
            'photos.*' => 'file|mimes:jpg,jpeg,png,webp|max:20480',
            'zip' => 'required_without:photos|file|mimes:zip|max:524288',
        ];
    }

    public function messages(): array
    {
        return [
            'photos.required_without' => 'Képek vagy ZIP fájl feltöltése kötelező.',
            'photos.array' => 'A képek listának kell lennie.',
            'photos.max' => 'Maximum 50 kép tölthető fel egyszerre.',
            'photos.*.file' => 'Érvénytelen fájl.',
            'photos.*.mimes' => 'Csak JPG, JPEG, PNG és WebP formátumok engedélyezettek.',
            'photos.*.max' => 'Egy kép maximum 20MB lehet.',
            'zip.required_without' => 'Képek vagy ZIP fájl feltöltése kötelező.',
            'zip.file' => 'Érvénytelen ZIP fájl.',
            'zip.mimes' => 'Csak ZIP formátum engedélyezett.',
            'zip.max' => 'A ZIP fájl maximum 512MB lehet.',
        ];
    }

    public function attributes(): array
    {
        return [
            'photos' => 'képek',
            'photos.*' => 'kép',
            'zip' => 'ZIP fájl',
        ];
    }
}

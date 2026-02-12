<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UploadAlbumPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photos' => 'required_without:zip|array|max:50',
            'photos.*' => 'file|mimes:jpg,jpeg,png,webp|max:20480', // max 20MB per file
            'zip' => 'required_without:photos|file|mimes:zip|max:524288', // max 512MB
        ];
    }

    public function messages(): array
    {
        return [
            'photos.required_without' => 'Képek vagy ZIP fájl megadása kötelező.',
            'photos.max' => 'Maximum 50 kép tölthető fel egyszerre.',
            'photos.*.mimes' => 'Csak JPG, PNG és WebP képek engedélyezettek.',
            'photos.*.max' => 'Maximum fájlméret: 20MB.',
            'zip.mimes' => 'Csak ZIP fájl engedélyezett.',
            'zip.max' => 'Maximum ZIP méret: 512MB.',
        ];
    }
}

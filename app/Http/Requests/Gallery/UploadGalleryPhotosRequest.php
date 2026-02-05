<?php

declare(strict_types=1);

namespace App\Http\Requests\Gallery;

use Illuminate\Foundation\Http\FormRequest;

class UploadGalleryPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photos' => 'required|array|min:1|max:50',
            'photos.*' => 'file|mimes:jpg,jpeg,png,webp,zip|max:51200',
        ];
    }

    public function messages(): array
    {
        return [
            'photos.required' => 'Legalább egy kép feltöltése kötelező.',
            'photos.max' => 'Maximum 50 fájl tölthető fel egyszerre.',
            'photos.*.mimes' => 'Csak JPG, PNG, WebP képek és ZIP archívumok engedélyezettek.',
            'photos.*.max' => 'Maximum fájlméret: 50MB.',
        ];
    }
}

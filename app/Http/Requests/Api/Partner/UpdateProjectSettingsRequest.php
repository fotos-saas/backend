<?php

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'max_retouch_photos' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'max_retouch_photos.integer' => 'A retusálható képek száma egész szám kell legyen.',
            'max_retouch_photos.min' => 'A retusálható képek száma legalább 1 kell legyen.',
            'max_retouch_photos.max' => 'A retusálható képek száma maximum 20 lehet.',
        ];
    }
}

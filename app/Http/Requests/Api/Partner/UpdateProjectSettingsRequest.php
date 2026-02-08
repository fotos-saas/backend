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
            'free_edit_window_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ];
    }

    public function messages(): array
    {
        return [
            'max_retouch_photos.integer' => 'A retusálható képek száma egész szám kell legyen.',
            'max_retouch_photos.min' => 'A retusálható képek száma legalább 1 kell legyen.',
            'max_retouch_photos.max' => 'A retusálható képek száma maximum 20 lehet.',
            'free_edit_window_hours.integer' => 'A módosítási időablak egész szám kell legyen.',
            'free_edit_window_hours.min' => 'A módosítási időablak legalább 1 óra kell legyen.',
            'free_edit_window_hours.max' => 'A módosítási időablak maximum 168 óra (1 hét) lehet.',
        ];
    }
}

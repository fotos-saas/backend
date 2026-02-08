<?php

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGlobalSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'default_max_retouch_photos' => ['nullable', 'integer', 'min:1', 'max:20'],
            'default_gallery_deadline_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'default_free_edit_window_hours' => ['nullable', 'integer', 'min:0', 'max:168'],
            'billing_enabled' => ['nullable', 'boolean'],
            'default_zip_content' => ['nullable', 'string', 'in:all,retouch_only,tablo_only,retouch_and_tablo'],
            'default_file_naming' => ['nullable', 'string', 'in:original,student_name,student_name_iptc'],
            'export_always_ask' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'default_max_retouch_photos.integer' => 'A retusálható képek száma egész szám kell legyen.',
            'default_max_retouch_photos.min' => 'A retusálható képek száma legalább 1 kell legyen.',
            'default_max_retouch_photos.max' => 'A retusálható képek száma maximum 20 lehet.',
            'default_gallery_deadline_days.integer' => 'A határidő napok száma egész szám kell legyen.',
            'default_gallery_deadline_days.min' => 'A határidő legalább 1 nap kell legyen.',
            'default_gallery_deadline_days.max' => 'A határidő maximum 90 nap lehet.',
            'default_free_edit_window_hours.integer' => 'A módosítási időablak egész szám kell legyen.',
            'default_free_edit_window_hours.min' => 'A módosítási időablak legalább 1 óra kell legyen.',
            'default_free_edit_window_hours.max' => 'A módosítási időablak maximum 168 óra (1 hét) lehet.',
            'default_zip_content.in' => 'Érvénytelen ZIP tartalom beállítás.',
            'default_file_naming.in' => 'Érvénytelen fájlnév stratégia.',
        ];
    }
}

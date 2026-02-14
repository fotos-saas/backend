<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class BulkPhotoUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:tablo_schools,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'set_active' => ['sometimes', 'boolean'],
            'assignments' => ['required', 'string'],
            'photos' => ['required', 'array', 'min:1', 'max:500'],
            'photos.*' => ['required', 'file', 'image', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'school_id.required' => 'Az iskola kiválasztása kötelező.',
            'year.required' => 'Az évszám megadása kötelező.',
            'assignments.required' => 'A párosítási adatok megadása kötelező.',
            'photos.required' => 'Legalább egy fotó szükséges.',
            'photos.max' => 'Legfeljebb 500 fotó tölthető fel egyszerre.',
            'photos.*.image' => 'Csak képfájlok tölthetők fel.',
            'photos.*.max' => 'Egy fájl mérete legfeljebb 20 MB lehet.',
        ];
    }

    /**
     * Parsed assignments (JSON -> array).
     */
    public function getAssignments(): array
    {
        return json_decode($this->input('assignments'), true) ?? [];
    }
}

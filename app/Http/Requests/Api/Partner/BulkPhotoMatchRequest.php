<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class BulkPhotoMatchRequest extends FormRequest
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
            'filenames' => ['required', 'array', 'min:1', 'max:500'],
            'filenames.*' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'school_id.required' => 'Az iskola kiválasztása kötelező.',
            'school_id.exists' => 'A kiválasztott iskola nem található.',
            'year.required' => 'Az évszám megadása kötelező.',
            'filenames.required' => 'Legalább egy fájlnév szükséges.',
            'filenames.max' => 'Legfeljebb 500 fájl tölthető fel egyszerre.',
        ];
    }
}

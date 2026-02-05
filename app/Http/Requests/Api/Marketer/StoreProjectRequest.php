<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Marketer;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => 'nullable|exists:tablo_schools,id',
            'class_name' => 'nullable|string|max:255',
            'class_year' => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'school_id.exists' => 'A megadott iskola nem található.',
            'class_name.max' => 'Az osztály neve maximum 255 karakter lehet.',
            'class_year.max' => 'Az évfolyam maximum 50 karakter lehet.',
        ];
    }
}

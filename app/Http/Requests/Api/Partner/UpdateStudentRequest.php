<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'canonical_name' => 'sometimes|required|string|max:255',
            'class_name' => 'nullable|string|max:50',
            'school_id' => 'sometimes|required|integer|exists:tablo_schools,id',
            'aliases' => 'nullable|array|max:10',
            'aliases.*' => 'string|max:255',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'canonical_name.required' => 'A diák neve kötelező.',
            'canonical_name.max' => 'A diák neve maximum 255 karakter lehet.',
            'class_name.max' => 'Az osztály neve maximum 50 karakter lehet.',
            'school_id.exists' => 'A kiválasztott iskola nem létezik.',
            'aliases.max' => 'Maximum 10 név variáns adható meg.',
            'aliases.*.max' => 'Egy név variáns maximum 255 karakter lehet.',
            'notes.max' => 'A megjegyzés maximum 2000 karakter lehet.',
        ];
    }

    public function attributes(): array
    {
        return [
            'canonical_name' => 'diák neve',
            'class_name' => 'osztály',
            'school_id' => 'iskola',
            'aliases' => 'név variánsok',
            'notes' => 'megjegyzés',
            'is_active' => 'aktív státusz',
        ];
    }
}

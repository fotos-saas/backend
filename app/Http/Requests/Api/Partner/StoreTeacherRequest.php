<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'canonical_name' => 'required|string|max:255',
            'title_prefix' => 'nullable|string|max:100',
            'school_id' => 'required|integer|exists:tablo_schools,id',
            'aliases' => 'nullable|array|max:10',
            'aliases.*' => 'string|max:255',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'canonical_name.required' => 'A tanár neve kötelező.',
            'canonical_name.max' => 'A tanár neve maximum 255 karakter lehet.',
            'title_prefix.max' => 'A titulus maximum 100 karakter lehet.',
            'school_id.required' => 'Az iskola kiválasztása kötelező.',
            'school_id.exists' => 'A kiválasztott iskola nem létezik.',
            'aliases.max' => 'Maximum 10 név variáns adható meg.',
            'aliases.*.max' => 'Egy név variáns maximum 255 karakter lehet.',
            'notes.max' => 'A megjegyzés maximum 2000 karakter lehet.',
        ];
    }

    public function attributes(): array
    {
        return [
            'canonical_name' => 'tanár neve',
            'title_prefix' => 'titulus',
            'school_id' => 'iskola',
            'aliases' => 'név variánsok',
            'notes' => 'megjegyzés',
        ];
    }
}

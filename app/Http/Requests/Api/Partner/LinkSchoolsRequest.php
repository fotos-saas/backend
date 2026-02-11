<?php

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class LinkSchoolsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_ids' => ['required', 'array', 'min:2'],
            'school_ids.*' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'school_ids.required' => 'Legalább két iskolát kell kiválasztani.',
            'school_ids.min' => 'Legalább két iskolát kell kiválasztani az összekapcsoláshoz.',
        ];
    }
}

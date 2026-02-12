<?php

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class LinkTeachersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'teacher_ids' => ['required', 'array', 'min:2'],
            'teacher_ids.*' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'teacher_ids.required' => 'Legalább két tanárt kell kiválasztani.',
            'teacher_ids.min' => 'Legalább két tanárt kell kiválasztani az összekapcsoláshoz.',
        ];
    }
}

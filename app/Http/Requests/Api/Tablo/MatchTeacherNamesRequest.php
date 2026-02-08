<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tablo;

use Illuminate\Foundation\Http\FormRequest;

class MatchTeacherNamesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'teacher_names' => 'required|array|min:1|max:20',
            'teacher_names.*' => 'string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'teacher_names.required' => 'Legalább egy tanárnév megadása kötelező.',
            'teacher_names.max' => 'Maximum 20 tanárnév adható meg egyszerre.',
        ];
    }
}

<?php

namespace App\Http\Requests\Api\Tablo;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo_date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:today',
                'before_or_equal:'.now()->addYear()->format('Y-m-d'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'photo_date.required' => 'A fotózás dátuma kötelező.',
            'photo_date.date' => 'Érvénytelen dátum formátum.',
            'photo_date.after_or_equal' => 'A dátum nem lehet a múltban.',
            'photo_date.before_or_equal' => 'A dátum maximum egy év múlva lehet.',
        ];
    }
}

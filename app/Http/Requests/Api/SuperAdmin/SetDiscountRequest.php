<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class SetDiscountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole('super_admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'percent' => ['required', 'integer', 'min:1', 'max:99'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'percent.required' => 'A kedvezmény mértéke kötelező.',
            'percent.integer' => 'A kedvezmény mértéke egész szám legyen.',
            'percent.min' => 'A kedvezmény minimum 1% lehet.',
            'percent.max' => 'A kedvezmény maximum 99% lehet.',
            'duration_months.integer' => 'Az időtartam egész szám legyen.',
            'duration_months.min' => 'Az időtartam minimum 1 hónap lehet.',
            'duration_months.max' => 'Az időtartam maximum 120 hónap (10 év) lehet.',
            'note.string' => 'A megjegyzés szöveg legyen.',
            'note.max' => 'A megjegyzés maximum 500 karakter lehet.',
        ];
    }
}

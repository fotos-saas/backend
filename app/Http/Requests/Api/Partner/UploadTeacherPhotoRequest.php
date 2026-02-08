<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UploadTeacherPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => 'required|image|max:51200',
            'year' => 'required|integer|min:2000|max:2100',
            'set_active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'A fotó feltöltése kötelező.',
            'photo.image' => 'A feltöltött fájl nem érvényes kép.',
            'photo.max' => 'A kép maximum 50 MB lehet.',
            'year.required' => 'Az évszám megadása kötelező.',
            'year.integer' => 'Az évszám egész szám kell legyen.',
            'year.min' => 'Az évszám minimum 2000 lehet.',
            'year.max' => 'Az évszám maximum 2100 lehet.',
        ];
    }

    public function attributes(): array
    {
        return [
            'photo' => 'fotó',
            'year' => 'évszám',
            'set_active' => 'aktív fotó',
        ];
    }
}

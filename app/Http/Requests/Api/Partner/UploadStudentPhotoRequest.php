<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UploadStudentPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => 'required|image|max:51200',
            'year' => 'required|integer|min:1990|max:2100',
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'Kép feltöltése kötelező.',
            'photo.image' => 'A fájlnak képnek kell lennie.',
            'photo.max' => 'A kép maximum 50 MB lehet.',
            'year.required' => 'Az évszám megadása kötelező.',
            'year.integer' => 'Az évszámnak számnak kell lennie.',
            'year.min' => 'Az évszám minimum 1990 lehet.',
            'year.max' => 'Az évszám maximum 2100 lehet.',
        ];
    }
}

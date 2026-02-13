<?php

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class OverridePersonPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo_id' => ['present', 'nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo_id.present' => 'A photo_id mező kötelező (null értékkel visszaállításhoz).',
            'photo_id.integer' => 'A photo_id-nak egész számnak kell lennie.',
        ];
    }
}

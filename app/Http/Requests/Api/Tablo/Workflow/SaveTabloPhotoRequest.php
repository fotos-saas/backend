<?php

namespace App\Http\Requests\Api\Tablo\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class SaveTabloPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workSessionId' => 'required|exists:tablo_galleries,id',
            'photoId' => 'required|exists:media,id',
        ];
    }

    public function messages(): array
    {
        return [
            'workSessionId.required' => 'A galéria azonosító kötelező.',
            'workSessionId.exists' => 'A megadott galéria nem található.',
            'photoId.required' => 'A tablókép azonosító kötelező.',
            'photoId.exists' => 'A kiválasztott kép nem található.',
        ];
    }
}

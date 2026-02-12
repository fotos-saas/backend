<?php

namespace App\Http\Requests\Api\Tablo\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class SavePhotoSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workSessionId' => 'required|exists:tablo_galleries,id',
            'photoIds' => 'present|array',
            'photoIds.*' => 'exists:media,id',
        ];
    }

    public function messages(): array
    {
        return [
            'workSessionId.required' => 'A galéria azonosító kötelező.',
            'workSessionId.exists' => 'A megadott galéria nem található.',
            'photoIds.present' => 'A képek listája kötelező.',
            'photoIds.array' => 'A képek listája érvénytelen formátumú.',
            'photoIds.*.exists' => 'A kiválasztott kép nem található.',
        ];
    }
}

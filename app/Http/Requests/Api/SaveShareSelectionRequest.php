<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SaveShareSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selections' => ['required', 'array'],
            'selections.*.photoId' => ['required', 'exists:photos,id'],
            'selections.*.selected' => ['required', 'boolean'],
            'selections.*.quantity' => ['required', 'integer', 'min:0'],
            'selections.*.notes' => ['nullable', 'string'],
        ];
    }
}

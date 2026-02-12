<?php

namespace App\Http\Requests\Api\Tablo\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class SaveCartCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workSessionId' => 'required|exists:tablo_galleries,id',
            'comment' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'workSessionId.required' => 'A galéria azonosító kötelező.',
            'workSessionId.exists' => 'A megadott galéria nem található.',
            'comment.required' => 'A megjegyzés megadása kötelező.',
            'comment.string' => 'A megjegyzés szöveg típusú kell legyen.',
        ];
    }
}

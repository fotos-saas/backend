<?php

namespace App\Http\Requests\Api\Tablo\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class MoveToStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workSessionId' => 'required|exists:tablo_galleries,id',
            'targetStep' => 'required|in:claiming,registration,retouch,tablo',
        ];
    }

    public function messages(): array
    {
        return [
            'workSessionId.required' => 'A galéria azonosító kötelező.',
            'workSessionId.exists' => 'A megadott galéria nem található.',
            'targetStep.required' => 'A cél lépés megadása kötelező.',
            'targetStep.in' => 'A megadott lépés érvénytelen.',
        ];
    }
}

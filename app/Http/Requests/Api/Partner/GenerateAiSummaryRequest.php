<?php

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class GenerateAiSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:5000'],
        ];
    }
}

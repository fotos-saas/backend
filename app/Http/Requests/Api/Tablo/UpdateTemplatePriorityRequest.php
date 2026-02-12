<?php

namespace App\Http\Requests\Api\Tablo;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTemplatePriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'priority' => 'required|integer|min:1|max:10',
        ];
    }
}

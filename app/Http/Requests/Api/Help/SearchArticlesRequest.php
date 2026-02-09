<?php

namespace App\Http\Requests\Api\Help;

use Illuminate\Foundation\Http\FormRequest;

class SearchArticlesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => 'required|string|max:200',
            'category' => 'nullable|string|max:50',
        ];
    }
}

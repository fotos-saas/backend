<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\BugReport;

use Illuminate\Foundation\Http\FormRequest;

class StoreBugReportCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:10000',
            'is_internal' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'A komment szövege kötelező.',
            'content.max' => 'A komment maximum 10000 karakter lehet.',
        ];
    }
}

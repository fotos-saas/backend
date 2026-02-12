<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RequestMagicLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'work_session_code' => ['nullable', 'string', 'size:6'],
            'include_code' => ['nullable', 'boolean'],
        ];
    }
}

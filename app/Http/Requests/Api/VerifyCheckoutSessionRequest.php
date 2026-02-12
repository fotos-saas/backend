<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class VerifyCheckoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => ['required', 'string'],
        ];
    }
}

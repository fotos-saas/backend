<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class BulkWorkSessionInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'emails' => ['required', 'array', 'max:30'],
            'emails.*' => ['required', 'email'],
        ];
    }
}

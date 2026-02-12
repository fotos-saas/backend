<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SendShareLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'albumId' => ['required', 'exists:albums,id'],
            'email' => ['required', 'email'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DevLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_type'  => ['required', 'string', 'in:partner,marketer,designer,printer,assistant,tablo-guest,partner-client'],
            'identifier' => ['required', 'integer', 'min:1'],
        ];
    }
}

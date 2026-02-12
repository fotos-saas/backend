<?php

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class CreateInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', 'in:designer,marketer,printer,assistant'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Az email cím megadása kötelező.',
            'email.email' => 'Érvénytelen email cím.',
            'role.required' => 'A szerepkör megadása kötelező.',
            'role.in' => 'Érvénytelen szerepkör.',
        ];
    }
}

<?php

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'in:designer,marketer,printer,assistant'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'A szerepkör megadása kötelező.',
            'role.in' => 'Érvénytelen szerepkör.',
        ];
    }
}

<?php

namespace App\Http\Requests\Api;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', new StrongPassword],
        ];
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'A jelenlegi jelszó megadása kötelező.',
            'current_password.current_password' => 'A jelenlegi jelszó nem megfelelő.',
            'password.required' => 'Az új jelszó megadása kötelező.',
            'password.confirmed' => 'A két jelszó nem egyezik.',
            'password.different' => 'Az új jelszó nem egyezhet meg a jelenlegivel.',
        ];
    }
}

<?php

namespace App\Http\Requests\Api;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
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
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
        ];
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'token.required' => 'A visszaállítási token kötelező.',
            'email.required' => 'Az email cím megadása kötelező.',
            'email.email' => 'Érvényes email címet adj meg.',
            'password.required' => 'Az új jelszó megadása kötelező.',
            'password.confirmed' => 'A két jelszó nem egyezik.',
        ];
    }
}

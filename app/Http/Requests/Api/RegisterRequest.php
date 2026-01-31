<?php

namespace App\Http\Requests\Api;

use App\Rules\StrongPassword;
use App\Services\AuthenticationService;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if registration is enabled
        $authService = app(AuthenticationService::class);

        return $authService->isRegistrationEnabled();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'name.required' => 'A név megadása kötelező.',
            'email.required' => 'Az email cím megadása kötelező.',
            'email.email' => 'Érvényes email címet adj meg.',
            'email.unique' => 'Ez az email cím már regisztrálva van.',
            'password.required' => 'A jelszó megadása kötelező.',
            'password.confirmed' => 'A két jelszó nem egyezik.',
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        throw new \Illuminate\Auth\Access\AuthorizationException(
            'A regisztráció jelenleg nem elérhető.'
        );
    }
}

<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class QrRegistrationRequest extends FormRequest
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
            'code' => ['required', 'string', 'size:8', 'alpha_num'],
            'name' => ['required', 'string', 'max:255', 'min:2'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'code.required' => 'A regisztrációs kód megadása kötelező.',
            'code.size' => 'A regisztrációs kód 8 karakterből áll.',
            'code.alpha_num' => 'A regisztrációs kód csak betűket és számokat tartalmazhat.',
            'name.required' => 'A név megadása kötelező.',
            'name.min' => 'A név legalább 2 karakter hosszú legyen.',
            'email.required' => 'Az email cím megadása kötelező.',
            'email.email' => 'Érvényes email címet adj meg.',
        ];
    }
}

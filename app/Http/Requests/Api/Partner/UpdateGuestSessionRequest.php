<?php

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuestSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guest_name' => ['sometimes', 'string', 'max:255'],
            'guest_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'guest_name.max' => 'A név maximum 255 karakter lehet.',
            'guest_email.email' => 'Érvényes email címet adjon meg.',
            'guest_email.max' => 'Az email maximum 255 karakter lehet.',
        ];
    }
}

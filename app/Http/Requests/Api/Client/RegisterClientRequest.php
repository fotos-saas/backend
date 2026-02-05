<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Client;

use App\Models\PartnerClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $client = $this->attributes->get('client');

        return [
            'email' => [
                'required',
                'email',
                'max:255',
                function ($attribute, $value, $fail) use ($client) {
                    if (!$client) {
                        return;
                    }
                    $exists = PartnerClient::registered()
                        ->byEmail($value)
                        ->where('id', '!=', $client->id)
                        ->exists();

                    if ($exists) {
                        $fail('Ez az email cím már használatban van.');
                    }
                },
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->numbers(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Az email cím megadása kötelező.',
            'email.email' => 'Érvénytelen email cím.',
            'password.required' => 'A jelszó megadása kötelező.',
            'password.confirmed' => 'A jelszavak nem egyeznek.',
            'password.min' => 'A jelszónak legalább 8 karakter hosszúnak kell lennie.',
        ];
    }
}

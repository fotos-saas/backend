<?php

namespace App\Http\Requests\Api\Tablo\Guest;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Közös FormRequest session_token validációhoz.
 *
 * Használva: validate, heartbeat, sessionStatus, checkVerificationStatus
 */
class SessionTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_token' => 'required|string|uuid',
        ];
    }
}

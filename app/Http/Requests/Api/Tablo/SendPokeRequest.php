<?php

namespace App\Http\Requests\Api\Tablo;

use Illuminate\Foundation\Http\FormRequest;

class SendPokeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_id' => 'required|integer|exists:tablo_guest_sessions,id',
            'category' => 'sometimes|in:voting,photoshoot,image_selection,general',
            'preset_key' => 'nullable|string|max:50',
            'custom_message' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'target_id.required' => 'A célpont megadása kötelező.',
            'target_id.exists' => 'A célpont nem található.',
        ];
    }
}

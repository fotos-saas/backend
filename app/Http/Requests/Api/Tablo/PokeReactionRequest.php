<?php

namespace App\Http\Requests\Api\Tablo;

use App\Models\TabloPoke;
use Illuminate\Foundation\Http\FormRequest;

class PokeReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reaction' => 'required|string|in:' . implode(',', TabloPoke::REACTIONS),
        ];
    }

    public function messages(): array
    {
        return [
            'reaction.required' => 'Reakció megadása kötelező.',
            'reaction.in' => 'Érvénytelen reakció.',
        ];
    }
}

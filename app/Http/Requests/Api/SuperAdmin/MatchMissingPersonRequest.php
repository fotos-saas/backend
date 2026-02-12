<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class MatchMissingPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin');
    }

    public function rules(): array
    {
        return [
            'person_id' => ['required', 'integer', 'exists:tablo_persons,id'],
            'media_id' => ['required', 'integer', 'exists:media,id'],
        ];
    }
}

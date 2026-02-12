<?php

namespace App\Http\Requests\Api\Tablo;

use Illuminate\Foundation\Http\FormRequest;

class ToggleDiscussionPostReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reaction' => 'nullable|string|in:💀,😭,🫡,❤️,👀',
        ];
    }
}

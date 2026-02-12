<?php

namespace App\Http\Requests\Api\Tablo;

use Illuminate\Foundation\Http\FormRequest;

class CreateNewsfeedCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:1000|min:1',
            'parent_id' => 'nullable|integer|exists:tablo_newsfeed_comments,id',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'A hozzászólás megadása kötelező.',
            'content.max' => 'A hozzászólás maximum 1000 karakter lehet.',
            'parent_id.exists' => 'A szülő hozzászólás nem található.',
        ];
    }
}

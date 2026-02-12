<?php

namespace App\Http\Requests\Api\Tablo;

use Illuminate\Foundation\Http\FormRequest;

class CreateDiscussionPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:10000|min:1',
            'parent_id' => 'nullable|integer|exists:tablo_discussion_posts,id',
            'media' => 'nullable|array|max:3',
            'media.*' => 'file|image|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'A tartalom megadása kötelező.',
            'media.max' => 'Maximum 3 kép csatolható.',
            'media.*.max' => 'A fájl mérete maximum 5MB lehet.',
            'media.*.image' => 'Csak képfájlok (jpg, png, gif, webp) engedélyezettek.',
        ];
    }
}

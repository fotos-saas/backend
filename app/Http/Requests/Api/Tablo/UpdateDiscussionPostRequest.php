<?php

namespace App\Http\Requests\Api\Tablo;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiscussionPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:10000|min:1',
            'media' => 'nullable|array|max:3',
            'media.*' => 'file|image|max:5120',
            'delete_media' => 'nullable|array',
            'delete_media.*' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'media.max' => 'Maximum 3 kép csatolható.',
            'media.*.max' => 'A fájl mérete maximum 5MB lehet.',
            'media.*.image' => 'Csak képfájlok (jpg, png, gif, webp) engedélyezettek.',
        ];
    }
}

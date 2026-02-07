<?php

namespace App\Http\Requests\Api\Tablo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Newsfeed bejegyzés frissítés validáció.
 */
class UpdateNewsfeedPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => 'string|max:255|min:3',
            'content' => 'nullable|string|max:5000',
            'event_date' => 'nullable|date',
            'event_time' => 'nullable|date_format:H:i',
            'event_location' => 'nullable|string|max:255',
            'media' => 'nullable|array',
            'media.*' => 'file|mimes:jpg,jpeg,png,gif,webp,mp4|max:10240',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'media.*.max' => 'A fájl mérete maximum 10MB lehet.',
        ];
    }
}

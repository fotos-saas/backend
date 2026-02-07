<?php

namespace App\Http\Requests\Api\Tablo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Newsfeed bejegyzés létrehozás validáció.
 */
class StoreNewsfeedPostRequest extends FormRequest
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
            'post_type' => 'required|in:announcement,event',
            'title' => 'required|string|max:255|min:3',
            'content' => 'nullable|string|max:5000',
            'event_date' => 'required_if:post_type,event|nullable|date|after:today',
            'event_time' => 'nullable|date_format:H:i',
            'event_location' => 'nullable|string|max:255',
            'media' => 'nullable|array|max:5',
            'media.*' => 'file|mimes:jpg,jpeg,png,gif,webp,mp4|max:10240',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'A cím megadása kötelező.',
            'title.min' => 'A cím legalább 3 karakter legyen.',
            'event_date.required_if' => 'Eseménynél a dátum megadása kötelező.',
            'event_date.after' => 'Az esemény dátuma jövőbeli kell legyen.',
            'media.max' => 'Maximum 5 fájl csatolható.',
            'media.*.max' => 'A fájl mérete maximum 10MB lehet.',
        ];
    }
}

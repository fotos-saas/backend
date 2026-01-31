<?php

namespace App\Http\Requests\Api\Tablo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update Poll Request
 *
 * Szavazás módosítás validáció.
 */
class UpdatePollRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => 'string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'is_multiple_choice' => 'boolean',
            'max_votes_per_guest' => 'integer|min:1|max:10',
            'show_results_before_vote' => 'boolean',
            'use_for_finalization' => 'boolean',
            'close_at' => 'nullable|date',
            // Media files: max 5 files, max 10MB each
            'media' => 'nullable|array|max:5',
            'media.*' => 'image|mimes:jpeg,jpg,png,gif,webp|max:10240', // 10MB
            // Media files to delete
            'delete_media_ids' => 'nullable|array',
            'delete_media_ids.*' => 'integer',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.max' => 'A cím maximum 255 karakter lehet.',
            'max_votes_per_guest.min' => 'Minimum 1 szavazat engedélyezett.',
            'max_votes_per_guest.max' => 'Maximum 10 szavazat engedélyezett.',
            'media.max' => 'Maximum 5 kép tölthető fel.',
            'media.*.image' => 'Csak képfájl tölthető fel.',
            'media.*.mimes' => 'A kép csak JPEG, PNG, GIF vagy WebP formátumú lehet.',
            'media.*.max' => 'Egy kép maximum 10MB lehet.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'title' => 'cím',
            'description' => 'leírás',
            'close_at' => 'zárási dátum',
            'max_votes_per_guest' => 'max szavazat vendégenként',
            'media' => 'képek',
            'media.*' => 'kép',
        ];
    }
}

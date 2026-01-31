<?php

namespace App\Http\Requests\Api\Tablo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Store Poll Request
 *
 * Új szavazás létrehozás validáció.
 */
class StorePollRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'cover_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            // Media files: max 5 files, max 10MB each
            'media' => 'nullable|array|max:5',
            'media.*' => 'image|mimes:jpeg,jpg,png,gif,webp|max:10240', // 10MB
            'type' => 'required|string|in:template,custom',
            'is_free_choice' => 'boolean',
            'is_multiple_choice' => 'boolean',
            'max_votes_per_guest' => 'integer|min:1|max:10',
            'show_results_before_vote' => 'boolean',
            'use_for_finalization' => 'boolean',
            'close_at' => 'nullable|date|after:now',
            'options' => 'required_if:is_free_choice,false|array|min:2',
            'options.*.label' => 'required|string|max:255',
            'options.*.description' => 'nullable|string|max:500',
            'options.*.template_id' => 'nullable|integer|exists:tablo_sample_templates,id',
            'options.*.image_url' => 'nullable|url|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'A cím megadása kötelező.',
            'title.max' => 'A cím maximum 255 karakter lehet.',
            'cover_image.image' => 'A borítókép érvénytelen formátumú.',
            'cover_image.mimes' => 'A borítókép csak JPEG, PNG vagy WebP formátumú lehet.',
            'cover_image.max' => 'A borítókép maximum 5MB lehet.',
            'media.max' => 'Maximum 5 kép tölthető fel.',
            'media.*.image' => 'Csak képfájl tölthető fel.',
            'media.*.mimes' => 'A kép csak JPEG, PNG, GIF vagy WebP formátumú lehet.',
            'media.*.max' => 'Egy kép maximum 10MB lehet.',
            'type.required' => 'A típus megadása kötelező.',
            'type.in' => 'A típus csak "template" vagy "custom" lehet.',
            'options.required_if' => 'Legalább 2 opció megadása kötelező.',
            'options.min' => 'Legalább 2 opció megadása kötelező.',
            'options.*.label.required' => 'Az opció neve kötelező.',
            'options.*.label.max' => 'Az opció neve maximum 255 karakter lehet.',
            'close_at.after' => 'A zárási dátum a jövőben kell legyen.',
            'max_votes_per_guest.min' => 'Minimum 1 szavazat engedélyezett.',
            'max_votes_per_guest.max' => 'Maximum 10 szavazat engedélyezett.',
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
            'type' => 'típus',
            'options' => 'opciók',
            'close_at' => 'zárási dátum',
            'max_votes_per_guest' => 'max szavazat vendégenként',
            'cover_image' => 'borítókép',
            'media' => 'képek',
            'media.*' => 'kép',
        ];
    }
}

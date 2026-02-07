<?php

namespace App\Http\Requests\Api\Tablo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update Discussion Request
 *
 * Beszélgetés frissítés validáció.
 */
class UpdateDiscussionRequest extends FormRequest
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
            'title' => 'string|max:255|min:3',
            'template_id' => 'nullable|integer|exists:tablo_sample_templates,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.min' => 'A cím legalább 3 karakter legyen.',
            'title.max' => 'A cím maximum 255 karakter lehet.',
        ];
    }
}

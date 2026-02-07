<?php

namespace App\Http\Requests\Api\Tablo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Store Discussion Request
 *
 * Új beszélgetés létrehozás validáció.
 */
class StoreDiscussionRequest extends FormRequest
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
            'title' => 'required|string|max:255|min:3',
            'content' => 'required|string|max:10000|min:10',
            'template_id' => 'nullable|integer|exists:tablo_sample_templates,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'A cím megadása kötelező.',
            'title.min' => 'A cím legalább 3 karakter legyen.',
            'title.max' => 'A cím maximum 255 karakter lehet.',
            'content.required' => 'A tartalom megadása kötelező.',
            'content.min' => 'A tartalom legalább 10 karakter legyen.',
            'content.max' => 'A tartalom maximum 10000 karakter lehet.',
        ];
    }
}

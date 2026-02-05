<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderAlbumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'max_selections' => 'nullable|integer|min:1',
            'min_selections' => 'nullable|integer|min:1',
            'max_retouch_photos' => 'nullable|integer|min:1|max:20',
            'status' => 'sometimes|in:draft,claiming',
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Az album neve maximum 255 karakter lehet.',
        ];
    }
}

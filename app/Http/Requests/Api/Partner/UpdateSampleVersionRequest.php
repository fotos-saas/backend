<?php

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSampleVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'string', 'max:2000'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['image', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp'],
            'delete_image_ids' => ['sometimes', 'array'],
            'delete_image_ids.*' => ['integer'],
        ];
    }
}

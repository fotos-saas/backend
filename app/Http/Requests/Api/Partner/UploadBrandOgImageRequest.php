<?php

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UploadBrandOgImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'og_image' => ['required', 'file', 'mimes:png,jpg,jpeg,svg,svgz', 'max:5120'],
        ];
    }
}

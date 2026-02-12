<?php

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UploadBrandLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,svg,svgz', 'max:2048'],
        ];
    }
}

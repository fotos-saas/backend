<?php

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UploadBrandFaviconRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'favicon' => ['required', 'file', 'mimes:png,jpg,jpeg,svg,svgz', 'max:512'],
        ];
    }
}

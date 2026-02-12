<?php

namespace App\Http\Requests\Api\Tablo\Finalization;

use Illuminate\Foundation\Http\FormRequest;

class UploadFinalizationFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|max:65536|mimes:jpg,jpeg,png,gif,webp,pdf,zip',
            'type' => 'required|string|in:background,attachment',
        ];
    }
}

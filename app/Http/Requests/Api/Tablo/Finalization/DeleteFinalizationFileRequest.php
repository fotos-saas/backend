<?php

namespace App\Http\Requests\Api\Tablo\Finalization;

use Illuminate\Foundation\Http\FormRequest;

class DeleteFinalizationFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fileId' => 'required|string|max:500',
        ];
    }
}

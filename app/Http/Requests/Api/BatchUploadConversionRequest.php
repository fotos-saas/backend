<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class BatchUploadConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'files' => 'required|array',
            'files.*' => 'file|max:204800|extensions:heic,heif,webp,avif,jxl,dng,cr2,nef,arw,orf,rw2,jpeg,jpg,png,bmp,zip',
            'job_id' => 'nullable|exists:conversion_jobs,id',
            'folder_paths' => 'nullable|array',
            'skip_conversions' => 'nullable|boolean',
        ];
    }
}

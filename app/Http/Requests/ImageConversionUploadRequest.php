<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImageConversionUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Public endpoint, no authorization required
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // ZIP fájlokhoz nagyobb limit (200MB), képekhez 50MB
        $file = $this->file('file');
        $isZip = $file && in_array(strtolower($file->getClientOriginalExtension()), ['zip']);
        $maxSize = $isZip ? 204800 : 51200; // 200MB ZIP, 50MB képek

        return [
            'file' => [
                'required',
                'file',
                "max:{$maxSize}",
                // MIME type validation for security (prevents extension spoofing)
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/heic,image/heif,image/avif,image/jxl,image/x-adobe-dng,image/x-canon-cr2,image/x-nikon-nef,image/x-sony-arw,image/x-olympus-orf,image/x-panasonic-rw2,image/bmp,application/zip,application/x-zip-compressed',
                // Extension validation for user-friendly error messages
                // Supported: Apple (HEIC/HEIF), Modern (AVIF/WEBP/JXL), RAW (DNG/CR2/NEF/ARW/ORF/RW2), Traditional, ZIP
                'extensions:heic,heif,webp,avif,jxl,dng,cr2,nef,arw,orf,rw2,jpeg,jpg,png,bmp,zip',
            ],
            'folder_path' => ['nullable', 'string', 'max:500'],
            'job_id' => ['nullable', 'exists:conversion_jobs,id'],
        ];
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Kérjük válasszon képfájlt.',
            'file.file' => 'A feltöltött fájl nem érvényes.',
            'file.max' => 'A fájl mérete maximum 50MB lehet.',
            'file.extensions' => 'Csak HEIC, HEIF, WEBP, JPEG, PNG, BMP formátumú képek vagy ZIP fájlok engedélyezettek.',
            'job_id.exists' => 'A megadott munka nem található.',
        ];
    }
}

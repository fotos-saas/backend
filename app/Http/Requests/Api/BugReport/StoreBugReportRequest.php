<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\BugReport;

use App\Models\BugReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBugReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:50000',
            'priority' => ['nullable', 'string', Rule::in(array_keys(BugReport::getPriorities()))],
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'image|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'A cím megadása kötelező.',
            'title.max' => 'A cím maximum 255 karakter lehet.',
            'description.required' => 'A leírás megadása kötelező.',
            'description.max' => 'A leírás maximum 50000 karakter lehet.',
            'priority.in' => 'Érvénytelen prioritás.',
            'attachments.max' => 'Maximum 5 melléklet csatolható.',
            'attachments.*.image' => 'Csak képfájlok tölthetők fel.',
            'attachments.*.mimes' => 'Megengedett formátumok: jpg, png, gif, webp.',
            'attachments.*.max' => 'Egy kép maximum 5 MB lehet.',
        ];
    }
}

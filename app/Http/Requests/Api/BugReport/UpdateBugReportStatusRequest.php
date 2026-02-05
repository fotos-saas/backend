<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\BugReport;

use App\Models\BugReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBugReportStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_keys(BugReport::getStatuses()))],
            'note' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'A státusz megadása kötelező.',
            'status.in' => 'Érvénytelen státusz.',
            'note.max' => 'A megjegyzés maximum 1000 karakter lehet.',
        ];
    }
}

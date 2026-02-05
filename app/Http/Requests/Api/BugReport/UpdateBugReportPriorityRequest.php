<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\BugReport;

use App\Models\BugReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBugReportPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'priority' => ['required', 'string', Rule::in(array_keys(BugReport::getPriorities()))],
        ];
    }

    public function messages(): array
    {
        return [
            'priority.required' => 'A prioritás megadása kötelező.',
            'priority.in' => 'Érvénytelen prioritás.',
        ];
    }
}

<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreHelpTourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => 'required|string|max:100|unique:help_tours,key',
            'title' => 'required|string|max:255',
            'trigger_route' => 'required|string|max:255',
            'target_roles' => 'array',
            'target_roles.*' => 'string',
            'target_plans' => 'array',
            'target_plans.*' => 'string',
            'trigger_type' => 'string|in:first_visit,manual,always',
            'is_active' => 'boolean',
            'steps' => 'array',
            'steps.*.title' => 'required|string|max:255',
            'steps.*.content' => 'required|string',
            'steps.*.target_selector' => 'nullable|string|max:255',
            'steps.*.placement' => 'string|in:top,bottom,left,right',
            'steps.*.highlight_type' => 'string|in:spotlight,border,none',
            'steps.*.allow_skip' => 'boolean',
        ];
    }
}

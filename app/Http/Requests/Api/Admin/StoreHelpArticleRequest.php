<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreHelpArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'required|string|max:50',
            'target_roles' => 'array',
            'target_roles.*' => 'string',
            'target_plans' => 'array',
            'target_plans.*' => 'string',
            'related_routes' => 'array',
            'related_routes.*' => 'string',
            'keywords' => 'array',
            'keywords.*' => 'string',
            'feature_key' => 'nullable|string|max:100',
            'is_published' => 'boolean',
            'is_faq' => 'boolean',
            'sort_order' => 'integer|min:0',
        ];
    }
}

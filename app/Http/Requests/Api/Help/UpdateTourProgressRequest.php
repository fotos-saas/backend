<?php

namespace App\Http\Requests\Api\Help;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTourProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:started,completed,skipped',
            'step_number' => 'required|integer|min:0',
        ];
    }
}

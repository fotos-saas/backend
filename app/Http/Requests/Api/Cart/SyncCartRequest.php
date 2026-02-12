<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Cart;

use Illuminate\Foundation\Http\FormRequest;

class SyncCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array',
            'items.*.photoId' => 'required|integer|exists:photos,id',
            'items.*.printSizeId' => 'nullable|integer|exists:print_sizes,id',
            'items.*.qty' => 'required|integer|min:1',
            'workSessionId' => 'required|integer|exists:work_sessions,id',
            'packageId' => 'nullable|integer|exists:packages,id',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'A kosár elemek megadása kötelező.',
            'items.*.photoId.required' => 'A kép kiválasztása kötelező minden elemnél.',
            'items.*.photoId.exists' => 'Az egyik megadott kép nem található.',
            'items.*.qty.required' => 'A mennyiség megadása kötelező minden elemnél.',
            'items.*.qty.min' => 'A mennyiségnek legalább 1-nek kell lennie.',
            'workSessionId.required' => 'A munkamenet kiválasztása kötelező.',
            'workSessionId.exists' => 'A megadott munkamenet nem található.',
        ];
    }
}

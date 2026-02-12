<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Közeli csomagpontok keresése validáció
 */
class SearchNearbyPackagePointsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|integer|min:100|max:50000',
            'provider' => 'nullable|in:foxpost,packeta',
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required' => 'A szélességi fok megadása kötelező.',
            'latitude.between' => 'A szélességi fok -90 és 90 között kell legyen.',
            'longitude.required' => 'A hosszúsági fok megadása kötelező.',
            'longitude.between' => 'A hosszúsági fok -180 és 180 között kell legyen.',
            'radius.min' => 'A sugár minimum 100 méter.',
            'radius.max' => 'A sugár maximum 50000 méter.',
            'provider.in' => 'A szolgáltató csak foxpost vagy packeta lehet.',
        ];
    }
}

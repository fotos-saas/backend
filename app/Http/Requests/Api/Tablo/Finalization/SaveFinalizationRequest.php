<?php

namespace App\Http\Requests\Api\Tablo\Finalization;

use Illuminate\Foundation\Http\FormRequest;

class SaveFinalizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'contactEmail' => 'required|email|max:255',
            'contactPhone' => ['required', 'string', 'max:50', 'regex:/^[\d\s\+\-\(\)]+$/'],
            'schoolName' => 'required|string|max:255',
            'schoolCity' => 'nullable|string|max:255',
            'className' => 'required|string|max:255',
            'classYear' => ['required', 'string', 'max:50'],
            'quote' => 'nullable|string|max:1000',
            'fontFamily' => 'nullable|string|max:255',
            'color' => ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'description' => 'nullable|string|max:5000',
            'sortType' => 'nullable|string|in:abc,kozepre,megjegyzesben,mindegy',
            'studentDescription' => 'required|string',
            'teacherDescription' => 'required|string',
            'acceptTerms' => 'required|accepted',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'A kapcsolattartó neve kötelező.',
            'contactEmail.required' => 'Az e-mail cím megadása kötelező.',
            'contactEmail.email' => 'Érvényes e-mail címet adj meg.',
            'contactPhone.required' => 'A telefonszám megadása kötelező.',
            'contactPhone.regex' => 'Érvényes telefonszámot adj meg.',
            'schoolName.required' => 'Az iskola neve kötelező.',
            'className.required' => 'Az osztály neve kötelező.',
            'classYear.required' => 'Az évfolyam megadása kötelező.',
            'color.regex' => 'Érvényes szín kódot adj meg (pl. #FF0000).',
            'studentDescription.required' => 'A diákok névsorának megadása kötelező.',
            'teacherDescription.required' => 'A tanárok névsorának megadása kötelező.',
            'acceptTerms.required' => 'Az ÁSZF elfogadása kötelező.',
            'acceptTerms.accepted' => 'Az ÁSZF elfogadása kötelező.',
        ];
    }
}

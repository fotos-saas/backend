<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use App\Models\TabloProject;
use Illuminate\Foundation\Http\FormRequest;

class SyncTeacherPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        $projectId = (int) $this->input('project_id');
        $project = TabloProject::find($projectId);

        if (!$project) {
            return false;
        }

        // Partner scope ellenőrzés
        $user = $this->user();
        $partner = $user?->getEffectivePartner();

        return $partner && $project->partner_id === $partner->id;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:tablo_projects,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'project_id.required' => 'A projekt azonosító megadása kötelező.',
            'project_id.exists' => 'A megadott projekt nem létezik.',
        ];
    }
}

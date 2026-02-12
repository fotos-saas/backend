<?php

namespace App\Actions\Tablo;

use App\Models\TabloProject;
use App\Models\TabloSampleTemplate;

/**
 * Minta kiválasztása projekthez.
 * Ellenőrzi a limitet és a duplikációt.
 */
class SelectTemplateAction
{
    /**
     * @return array{success: bool, error?: string, status?: int, templateId?: int, priority?: int, canSelectMore?: bool}
     */
    public function execute(TabloProject $project, int $templateId, ?int $priority = null): array
    {
        // Minta létezik-e
        $template = TabloSampleTemplate::active()->find($templateId);
        if (! $template) {
            return ['success' => false, 'error' => 'Minta nem található', 'status' => 404];
        }

        // Már ki van-e választva
        if ($project->hasSelectedTemplate($templateId)) {
            return ['success' => false, 'error' => 'Ez a minta már ki van választva', 'status' => 422];
        }

        // Maximum limit ellenőrzés
        if (! $project->canSelectMoreTemplates()) {
            $max = $project->max_template_selections ?? 3;

            return ['success' => false, 'error' => "Maximum {$max} minta választható", 'status' => 422];
        }

        // Kiválasztás prioritással
        $effectivePriority = $priority ?? $project->getNextTemplatePriority();

        $project->selectedTemplates()->attach($templateId, [
            'priority' => $effectivePriority,
        ]);

        return [
            'success' => true,
            'templateId' => $templateId,
            'priority' => $effectivePriority,
            'canSelectMore' => $project->canSelectMoreTemplates(),
        ];
    }
}

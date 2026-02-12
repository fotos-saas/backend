<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Helpers\QueryHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tablo\SelectTemplateRequest;
use App\Http\Requests\Api\Tablo\UpdateTemplatePriorityRequest;
use App\Models\TabloProject;
use App\Models\TabloSampleTemplate;
use App\Models\TabloSampleTemplateCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tablo Template Controller
 *
 * API végpontok a minta választó oldalhoz (frontend-tablo).
 * Minden végpont auth:sanctum + CheckTabloProjectStatus middleware mögött van.
 */
class TabloTemplateController extends Controller
{
    /**
     * Get template categories.
     */
    public function getCategories(): JsonResponse
    {
        $categories = TabloSampleTemplateCategory::active()
            ->ordered()
            ->withCount(['templates' => fn ($q) => $q->active()])
            ->get()
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'description' => $cat->description,
                'icon' => $cat->icon,
                'templateCount' => $cat->templates_count,
            ]);

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Get templates with pagination and filtering.
     * Supports: category filter, search, load more pagination.
     */
    public function getTemplates(Request $request): JsonResponse
    {
        $perPage = min($request->input('per_page', 12), 50);
        $page = max($request->input('page', 1), 1);
        $category = $request->input('category');
        $search = $request->input('search');
        $featured = $request->boolean('featured');

        $query = TabloSampleTemplate::active()
            ->with('categories:id,name,slug')
            ->ordered();

        // Filter by category
        if ($category) {
            $query->inCategory($category);
        }

        // Filter by search
        if ($search) {
            $pattern = QueryHelper::safeLikePattern($search);
            $query->where(function ($q) use ($pattern) {
                $q->where('name', 'ilike', $pattern)
                    ->orWhere('description', 'ilike', $pattern);
            });
        }

        // Filter featured only
        if ($featured) {
            $query->featured();
        }

        // Get total count before pagination
        $totalCount = $query->count();

        // Paginate
        $templates = $query
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(fn ($template) => $template->toApiResponse());

        return response()->json([
            'success' => true,
            'data' => $templates,
            'meta' => [
                'currentPage' => $page,
                'perPage' => $perPage,
                'totalCount' => $totalCount,
                'hasMore' => ($page * $perPage) < $totalCount,
            ],
        ]);
    }

    /**
     * Get single template details.
     */
    public function getTemplate(int $id): JsonResponse
    {
        $template = TabloSampleTemplate::active()
            ->with('categories:id,name,slug')
            ->find($id);

        if (! $template) {
            return response()->json([
                'success' => false,
                'message' => 'Minta nem található',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $template->toApiResponse(),
        ]);
    }

    /**
     * Get current project's template selections.
     */
    public function getSelections(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::with([
            'selectedTemplates' => fn ($q) => $q->active()->with('categories:id,name,slug'),
        ])->find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $selections = $tabloProject->selectedTemplates->map(fn ($template) => [
            ...$template->toApiResponse(),
            'priority' => $template->pivot->priority,
            'selectedAt' => $template->pivot->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'selections' => $selections,
                'maxSelections' => $tabloProject->max_template_selections ?? 3,
                'canSelectMore' => $tabloProject->canSelectMoreTemplates(),
            ],
        ]);
    }

    /**
     * Select a template (add to project selections).
     */
    public function selectTemplate(SelectTemplateRequest $request, int $templateId): JsonResponse
    {

        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        // Check if template exists
        $template = TabloSampleTemplate::active()->find($templateId);
        if (! $template) {
            return response()->json([
                'success' => false,
                'message' => 'Minta nem található',
            ], 404);
        }

        // Check if already selected
        if ($tabloProject->hasSelectedTemplate($templateId)) {
            return response()->json([
                'success' => false,
                'message' => 'Ez a minta már ki van választva',
            ], 422);
        }

        // Check max selections
        if (! $tabloProject->canSelectMoreTemplates()) {
            $max = $tabloProject->max_template_selections ?? 3;

            return response()->json([
                'success' => false,
                'message' => "Maximum {$max} minta választható",
            ], 422);
        }

        // Add selection with priority
        $priority = $request->input('priority', $tabloProject->getNextTemplatePriority());

        $tabloProject->selectedTemplates()->attach($templateId, [
            'priority' => $priority,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Minta kiválasztva',
            'data' => [
                'templateId' => $templateId,
                'priority' => $priority,
                'canSelectMore' => $tabloProject->canSelectMoreTemplates(),
            ],
        ]);
    }

    /**
     * Deselect a template (remove from project selections).
     */
    public function deselectTemplate(Request $request, int $templateId): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        // Check if selected
        if (! $tabloProject->hasSelectedTemplate($templateId)) {
            return response()->json([
                'success' => false,
                'message' => 'Ez a minta nincs kiválasztva',
            ], 422);
        }

        // Remove selection
        $tabloProject->selectedTemplates()->detach($templateId);

        // Reorder priorities
        $this->reorderPriorities($tabloProject);

        return response()->json([
            'success' => true,
            'message' => 'Minta eltávolítva',
            'data' => [
                'templateId' => $templateId,
                'canSelectMore' => $tabloProject->canSelectMoreTemplates(),
            ],
        ]);
    }

    /**
     * Update selection priority.
     */
    public function updatePriority(UpdateTemplatePriorityRequest $request, int $templateId): JsonResponse
    {

        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        // Check if selected
        if (! $tabloProject->hasSelectedTemplate($templateId)) {
            return response()->json([
                'success' => false,
                'message' => 'Ez a minta nincs kiválasztva',
            ], 422);
        }

        // Update priority
        $tabloProject->selectedTemplates()->updateExistingPivot($templateId, [
            'priority' => $request->input('priority'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prioritás frissítve',
        ]);
    }

    /**
     * Reorder priorities to be sequential (1, 2, 3...).
     */
    private function reorderPriorities(TabloProject $project): void
    {
        $selections = DB::table('tablo_project_template_selections')
            ->where('tablo_project_id', $project->id)
            ->orderBy('priority')
            ->get();

        $priority = 1;
        foreach ($selections as $selection) {
            DB::table('tablo_project_template_selections')
                ->where('id', $selection->id)
                ->update(['priority' => $priority++]);
        }
    }
}

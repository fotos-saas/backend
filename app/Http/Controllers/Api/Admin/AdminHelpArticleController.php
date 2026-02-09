<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use App\Services\Help\HelpKnowledgeBaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminHelpArticleController extends Controller
{
    public function __construct(
        private HelpKnowledgeBaseService $kbService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $builder = HelpArticle::query()->ordered();

        if ($request->query('category')) {
            $builder->where('category', $request->query('category'));
        }

        if ($request->query('published') !== null) {
            $builder->where('is_published', $request->boolean('published'));
        }

        $articles = $builder->paginate(20);

        return $this->paginatedResponse($articles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
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
        ]);

        $article = HelpArticle::create($validated);
        $this->kbService->invalidateCache();

        return $this->createdResponse($article, 'Cikk létrehozva');
    }

    public function show(HelpArticle $article): JsonResponse
    {
        return $this->successResponse($article);
    }

    public function update(Request $request, HelpArticle $article): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'string|max:255',
            'content' => 'string',
            'category' => 'string|max:50',
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
        ]);

        $article->update($validated);
        $this->kbService->invalidateCache();

        return $this->successResponse($article, 'Cikk frissítve');
    }

    public function destroy(HelpArticle $article): JsonResponse
    {
        $article->delete();
        $this->kbService->invalidateCache();

        return $this->successMessageResponse('Cikk törölve');
    }
}

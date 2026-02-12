<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreHelpArticleRequest;
use App\Http\Requests\Api\Admin\UpdateHelpArticleRequest;
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

    public function store(StoreHelpArticleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $article = HelpArticle::create($validated);
        $this->kbService->invalidateCache();

        return $this->createdResponse($article, 'Cikk létrehozva');
    }

    public function show(HelpArticle $article): JsonResponse
    {
        return $this->successResponse($article);
    }

    public function update(UpdateHelpArticleRequest $request, HelpArticle $article): JsonResponse
    {
        $validated = $request->validated();

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

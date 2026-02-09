<?php

namespace App\Http\Controllers\Api\Help;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Help\SearchArticlesRequest;
use App\Models\HelpArticle;
use App\Services\Help\HelpKnowledgeBaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpArticleController extends Controller
{
    public function __construct(
        private HelpKnowledgeBaseService $kbService,
    ) {}

    /**
     * Cikkek listázása.
     */
    public function index(Request $request): JsonResponse
    {
        $role = $request->query('role');
        $plan = $request->query('plan');
        $category = $request->query('category');

        $builder = HelpArticle::published()->ordered();

        if ($role) {
            $builder->where(function ($q) use ($role) {
                $q->whereJsonContains('target_roles', $role)
                    ->orWhereJsonLength('target_roles', 0);
            });
        }

        if ($plan) {
            $builder->where(function ($q) use ($plan) {
                $q->whereJsonContains('target_plans', $plan)
                    ->orWhereJsonLength('target_plans', 0);
            });
        }

        if ($category) {
            $builder->where('category', $category);
        }

        $articles = $builder->get(['id', 'title', 'slug', 'category', 'is_faq', 'target_roles', 'target_plans', 'keywords']);

        return $this->successResponse($articles);
    }

    /**
     * Cikk keresés.
     */
    public function search(SearchArticlesRequest $request): JsonResponse
    {
        $articles = $this->kbService->search(
            $request->validated('q'),
            $request->query('role'),
            $request->query('plan'),
            $request->query('route'),
        );

        return $this->successResponse($articles);
    }

    /**
     * Cikkek route alapján.
     */
    public function forRoute(Request $request): JsonResponse
    {
        $route = $request->query('route');
        if (! $route) {
            return $this->errorResponse('Route paraméter kötelező');
        }

        $articles = $this->kbService->getForRoute(
            $route,
            $request->query('role'),
            $request->query('plan'),
        );

        return $this->successResponse($articles);
    }

    /**
     * FAQ cikkek.
     */
    public function faq(Request $request): JsonResponse
    {
        $articles = $this->kbService->getFaq(
            $request->query('role'),
            $request->query('plan'),
        );

        return $this->successResponse($articles);
    }

    /**
     * Egyetlen cikk slug alapján.
     */
    public function show(string $slug): JsonResponse
    {
        $article = HelpArticle::published()->where('slug', $slug)->first();

        if (! $article) {
            return $this->notFoundResponse();
        }

        return $this->successResponse($article);
    }
}

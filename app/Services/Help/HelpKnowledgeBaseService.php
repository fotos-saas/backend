<?php

namespace App\Services\Help;

use App\Helpers\QueryHelper;
use App\Models\HelpArticle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class HelpKnowledgeBaseService
{
    /**
     * Cikkek keresése role+plan+route alapján.
     */
    public function search(string $query, ?string $role = null, ?string $plan = null, ?string $route = null, int $limit = 10): Collection
    {
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

        if ($route) {
            $builder->where(function ($q) use ($route) {
                $q->whereJsonContains('related_routes', $route)
                    ->orWhereJsonLength('related_routes', 0);
            });
        }

        if ($query) {
            $searchTerm = QueryHelper::safeLikePattern(mb_strtolower($query));
            $builder->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(title) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(content_plain) LIKE ?', [$searchTerm])
                    ->orWhereRaw("EXISTS (SELECT 1 FROM jsonb_array_elements_text(keywords) AS kw WHERE LOWER(kw) LIKE ?)", [$searchTerm]);
            });
        }

        return $builder->limit($limit)->get();
    }

    /**
     * Cikkek lekérése route alapján (chatbot context-hez).
     */
    public function getForRoute(string $route, ?string $role = null, ?string $plan = null, int $limit = 5): Collection
    {
        $cacheKey = "help:articles:route:".md5("{$route}:{$role}:{$plan}");

        return Cache::remember($cacheKey, 900, function () use ($route, $role, $plan, $limit) {
            $builder = HelpArticle::published()
                ->forRoute($route)
                ->ordered();

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

            return $builder->limit($limit)->get();
        });
    }

    /**
     * FAQ cikkek lekérése (cache-elve).
     */
    public function getFaq(?string $role = null, ?string $plan = null): Collection
    {
        $cacheKey = "help:articles:faq:".md5("{$role}:{$plan}");

        return Cache::remember($cacheKey, 1800, function () use ($role, $plan) {
            $builder = HelpArticle::published()->faq()->ordered();

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

            return $builder->get();
        });
    }

    /**
     * Cache invalidáció KB CRUD műveleteknél.
     */
    public function invalidateCache(): void
    {
        Cache::forget('help:articles:faq:'.md5(':'));

        // Pattern-based flush nem érhető el minden driver-nél,
        // ezért a route cache-ek természetesen lejárnak (15 perc TTL)
    }
}

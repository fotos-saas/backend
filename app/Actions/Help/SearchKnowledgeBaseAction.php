<?php

namespace App\Actions\Help;

use App\Services\Help\HelpKnowledgeBaseService;
use Illuminate\Support\Collection;

class SearchKnowledgeBaseAction
{
    public function __construct(
        private HelpKnowledgeBaseService $kbService,
    ) {}

    /**
     * KB keresés role+plan szűréssel.
     */
    public function execute(string $query, ?string $role = null, ?string $plan = null, ?string $route = null, int $limit = 10): Collection
    {
        return $this->kbService->search($query, $role, $plan, $route, $limit);
    }
}

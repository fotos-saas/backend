<?php

namespace App\Http\Controllers\Api\Tablo\Traits;

use App\Models\TabloProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trait: ResolvesTabloProject
 *
 * Segéd trait az entity-projekt összekapcsoláshoz.
 * A controllerben használva leegyszerűsíti az entitások
 * projekt-alapú lekérdezését.
 *
 * Használat:
 *   $poll = $this->findForProject(TabloPoll::class, $pollId, $request);
 *   if ($poll instanceof JsonResponse) return $poll;
 */
trait ResolvesTabloProject
{
    /**
     * Entitás keresése adott projekthez.
     * Ha nem található, JsonResponse-t ad vissza.
     *
     * @param  class-string<Model>  $modelClass
     * @param  int|string  $id  - Az entitás ID-je vagy egyéb azonosítója
     * @param  string  $projectForeignKey  - A projekt foreign key neve
     * @param  string  $notFoundMessage  - Hibaüzenet, ha nem található
     */
    protected function findForProject(
        string $modelClass,
        int|string $id,
        Request $request,
        string $projectForeignKey = 'tablo_project_id',
        string $notFoundMessage = 'Nem található'
    ): Model|JsonResponse {
        $projectId = $this->getProjectId($request);

        $entity = $modelClass::where('id', $id)
            ->where($projectForeignKey, $projectId)
            ->first();

        if (! $entity) {
            return $this->notFoundResponse($notFoundMessage);
        }

        return $entity;
    }

    /**
     * Entitás keresése slug alapján adott projekthez.
     */
    protected function findBySlugForProject(
        string $modelClass,
        string $slug,
        Request $request,
        string $projectForeignKey = 'tablo_project_id',
        string $notFoundMessage = 'Nem található'
    ): Model|JsonResponse {
        $projectId = $this->getProjectId($request);

        $entity = $modelClass::where('slug', $slug)
            ->where($projectForeignKey, $projectId)
            ->first();

        if (! $entity) {
            return $this->notFoundResponse($notFoundMessage);
        }

        return $entity;
    }

    /**
     * Query builder projekt-szűréssel.
     *
     * @param  class-string<Model>  $modelClass
     */
    protected function queryForProject(
        string $modelClass,
        Request $request,
        string $projectForeignKey = 'tablo_project_id'
    ): Builder {
        $projectId = $this->getProjectId($request);

        return $modelClass::where($projectForeignKey, $projectId);
    }

    /**
     * Entitás keresése kapcsolódó modellen keresztül.
     *
     * Példa: Post keresése Discussion-on keresztül
     *   $post = $this->findThroughRelation(
     *       TabloDiscussionPost::class,
     *       $postId,
     *       $request,
     *       'discussion',
     *       'Hozzászólás nem található'
     *   );
     *
     * @param  class-string<Model>  $modelClass
     * @param  string  $relationName  - A kapcsolat neve (pl. 'discussion')
     */
    protected function findThroughRelation(
        string $modelClass,
        int $id,
        Request $request,
        string $relationName,
        string $notFoundMessage = 'Nem található',
        string $projectForeignKey = 'tablo_project_id'
    ): Model|JsonResponse {
        $projectId = $this->getProjectId($request);

        $entity = $modelClass::with($relationName)
            ->whereHas($relationName, fn ($q) => $q->where($projectForeignKey, $projectId))
            ->find($id);

        if (! $entity) {
            return $this->notFoundResponse($notFoundMessage);
        }

        return $entity;
    }

    /**
     * Projekt létezésének ellenőrzése (gyors check).
     */
    protected function projectExists(Request $request): bool
    {
        $projectId = $this->getProjectId($request);

        return TabloProject::where('id', $projectId)->exists();
    }
}

<?php

namespace App\Services;

use App\Models\TabloProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Partner draft feltöltések kezelő service.
 *
 * Kezeli a félbehagyott képfeltöltéseket:
 * - Draft-ok listázása és részletek
 * - Draft folytatás és törlés
 * - Automatikus cleanup (30 nap után)
 */
class PartnerDraftService
{
    /**
     * Maximum draft életkor napokban
     */
    public const MAX_DRAFT_AGE_DAYS = 30;

    /**
     * Maximum drafts per project
     */
    public const MAX_DRAFTS_PER_PROJECT = 5;

    /**
     * Új draft session ID generálása.
     */
    public function createDraftSessionId(): string
    {
        return 'draft_' . now()->format('Ymd_His') . '_' . uniqid();
    }

    /**
     * Projekt draft-jainak listázása.
     *
     * @param TabloProject $project Projekt
     * @return Collection<array{id: string, photoCount: int, createdAt: string, lastModifiedAt: string, firstThumbUrl: string|null, mediaIds: int[], hasAssignments: bool}>
     */
    public function getDrafts(TabloProject $project): Collection
    {
        $pendingPhotos = $project->getMedia('tablo_pending');

        // Csoportosítás draft_session_id alapján
        $grouped = $pendingPhotos
            ->filter(fn (Media $media) => $media->getCustomProperty('draft_session_id'))
            ->groupBy(fn (Media $media) => $media->getCustomProperty('draft_session_id'));

        $results = $grouped->map(function (Collection $photos, string $draftId) use ($project) {
            $firstPhoto = $photos->first();
            $dates = $photos->map(fn ($p) => $p->getCustomProperty('draft_created_at') ?? $p->getCustomProperty('uploaded_at'));

            $createdAt = $dates->min();
            $lastModifiedAt = $dates->max();

            // Ellenőrizzük van-e mentett párosítás
            $assignments = $this->getAssignments($project, $draftId);

            return [
                'id' => $draftId,
                'photoCount' => $photos->count(),
                'createdAt' => $createdAt,
                'lastModifiedAt' => $lastModifiedAt,
                'firstThumbUrl' => $firstPhoto?->getUrl('thumb'),
                'mediaIds' => $photos->pluck('id')->toArray(),
                'hasAssignments' => ! empty($assignments),
                'assignmentCount' => count($assignments),
            ];
        });

        // Árva képek (nincs draft_session_id) - külön "orphan" draft-ként kezeljük
        $orphanPhotos = $pendingPhotos
            ->filter(fn (Media $media) => ! $media->getCustomProperty('draft_session_id'));

        if ($orphanPhotos->isNotEmpty()) {
            $firstOrphan = $orphanPhotos->first();
            $orphanDates = $orphanPhotos->map(fn ($p) => $p->getCustomProperty('uploaded_at') ?? $p->created_at->toIso8601String());

            $results->put('orphan', [
                'id' => 'orphan',
                'photoCount' => $orphanPhotos->count(),
                'createdAt' => $orphanDates->min(),
                'lastModifiedAt' => $orphanDates->max(),
                'firstThumbUrl' => $firstOrphan?->getUrl('thumb'),
                'mediaIds' => $orphanPhotos->pluck('id')->toArray(),
                'hasAssignments' => false,
                'assignmentCount' => 0,
                'isOrphan' => true, // Jelző, hogy ez árva képeket tartalmaz
            ]);
        }

        return $results
            ->sortByDesc('createdAt')
            ->values()
            ->take(self::MAX_DRAFTS_PER_PROJECT);
    }

    /**
     * Egyetlen draft részleteinek lekérése.
     *
     * @param TabloProject $project Projekt
     * @param string $draftId Draft ID
     * @return array|null Draft részletek vagy null ha nem található
     */
    public function getDraft(TabloProject $project, string $draftId): ?array
    {
        // Speciális eset: "orphan" - árva képek (nincs draft_session_id)
        if ($draftId === 'orphan') {
            $photos = $project->getMedia('tablo_pending')
                ->filter(fn (Media $media) => ! $media->getCustomProperty('draft_session_id'));
        } else {
            $photos = $project->getMedia('tablo_pending')
                ->filter(fn (Media $media) => $media->getCustomProperty('draft_session_id') === $draftId);
        }

        if ($photos->isEmpty()) {
            return null;
        }

        $dates = $photos->map(fn ($p) => $p->getCustomProperty('draft_created_at') ?? $p->getCustomProperty('uploaded_at') ?? $p->created_at->toIso8601String());

        return [
            'id' => $draftId,
            'photoCount' => $photos->count(),
            'createdAt' => $dates->min(),
            'lastModifiedAt' => $dates->max(),
            'firstThumbUrl' => $photos->first()?->getUrl('thumb'),
            'mediaIds' => $photos->pluck('id')->toArray(),
            'isOrphan' => $draftId === 'orphan',
            'photos' => $photos->map(fn (Media $media) => [
                'mediaId' => $media->id,
                'filename' => $media->file_name,
                'iptcTitle' => $media->getCustomProperty('iptc_title'),
                'thumbUrl' => $media->getUrl('thumb'),
                'fullUrl' => $media->getUrl(),
                'uploadedAt' => $media->getCustomProperty('uploaded_at'),
            ])->values()->toArray(),
        ];
    }

    /**
     * Draft folytatása - visszaadja a draft képeit és párosításait.
     *
     * @param TabloProject $project Projekt
     * @param string $draftId Draft ID
     * @return array|null Draft részletek vagy null ha nem található
     */
    public function continueDraft(TabloProject $project, string $draftId): ?array
    {
        $draft = $this->getDraft($project, $draftId);

        if (! $draft) {
            return null;
        }

        // Hozzáadjuk a mentett párosításokat is
        $draft['assignments'] = $this->getAssignments($project, $draftId);

        Log::info('PartnerDraft: Continuing draft', [
            'project_id' => $project->id,
            'draft_id' => $draftId,
            'photo_count' => $draft['photoCount'],
            'assignment_count' => count($draft['assignments']),
        ]);

        return $draft;
    }

    /**
     * Párosítások mentése draft-hoz.
     *
     * @param TabloProject $project Projekt
     * @param string $draftId Draft ID
     * @param array $assignments Párosítások [{personId: int, mediaId: int}, ...]
     */
    public function saveAssignments(TabloProject $project, string $draftId, array $assignments): void
    {
        // A projekt custom_properties-ben tároljuk a draft párosításokat
        $draftAssignments = $project->custom_properties['draft_assignments'] ?? [];
        $draftAssignments[$draftId] = [
            'assignments' => $assignments,
            'updatedAt' => now()->toIso8601String(),
        ];

        $project->custom_properties = array_merge(
            $project->custom_properties ?? [],
            ['draft_assignments' => $draftAssignments]
        );
        $project->save();

        Log::info('PartnerDraft: Assignments saved', [
            'project_id' => $project->id,
            'draft_id' => $draftId,
            'assignment_count' => count($assignments),
        ]);
    }

    /**
     * Párosítások lekérése draft-ból.
     *
     * @param TabloProject $project Projekt
     * @param string $draftId Draft ID
     * @return array Párosítások [{personId: int, mediaId: int}, ...]
     */
    public function getAssignments(TabloProject $project, string $draftId): array
    {
        $draftAssignments = $project->custom_properties['draft_assignments'] ?? [];

        return $draftAssignments[$draftId]['assignments'] ?? [];
    }

    /**
     * Párosítások törlése draft-ból.
     *
     * @param TabloProject $project Projekt
     * @param string $draftId Draft ID
     */
    public function clearAssignments(TabloProject $project, string $draftId): void
    {
        $draftAssignments = $project->custom_properties['draft_assignments'] ?? [];

        if (isset($draftAssignments[$draftId])) {
            unset($draftAssignments[$draftId]);
            $project->custom_properties = array_merge(
                $project->custom_properties ?? [],
                ['draft_assignments' => $draftAssignments]
            );
            $project->save();

            Log::info('PartnerDraft: Assignments cleared', [
                'project_id' => $project->id,
                'draft_id' => $draftId,
            ]);
        }
    }

    /**
     * Draft törlése - törli a draft összes képét.
     *
     * @param TabloProject $project Projekt
     * @param string $draftId Draft ID
     * @return bool Sikeres volt-e a törlés
     */
    public function deleteDraft(TabloProject $project, string $draftId): bool
    {
        // Speciális eset: "orphan" - árva képek (nincs draft_session_id)
        if ($draftId === 'orphan') {
            $photos = $project->getMedia('tablo_pending')
                ->filter(fn (Media $media) => ! $media->getCustomProperty('draft_session_id'));
        } else {
            $photos = $project->getMedia('tablo_pending')
                ->filter(fn (Media $media) => $media->getCustomProperty('draft_session_id') === $draftId);
        }

        if ($photos->isEmpty()) {
            return false;
        }

        $count = $photos->count();

        foreach ($photos as $photo) {
            $photo->delete();
        }

        Log::info('PartnerDraft: Draft deleted', [
            'project_id' => $project->id,
            'draft_id' => $draftId,
            'deleted_count' => $count,
        ]);

        return true;
    }

    /**
     * Draft finalizálása - eltávolítja a draft session ID-t a képekről és törli a párosításokat.
     *
     * @param TabloProject $project Projekt
     * @param string $draftId Draft ID
     */
    public function finalizeDraft(TabloProject $project, string $draftId): void
    {
        $photos = $project->getMedia('tablo_pending')
            ->filter(fn (Media $media) => $media->getCustomProperty('draft_session_id') === $draftId);

        foreach ($photos as $photo) {
            $photo->forgetCustomProperty('draft_session_id');
            $photo->forgetCustomProperty('draft_created_at');
            $photo->save();
        }

        // Párosítások törlése is
        $this->clearAssignments($project, $draftId);

        Log::info('PartnerDraft: Draft finalized', [
            'project_id' => $project->id,
            'draft_id' => $draftId,
            'photo_count' => $photos->count(),
        ]);
    }

    /**
     * Lejárt draft-ok törlése (30 nap után).
     *
     * @return int Törölt képek száma
     */
    public function cleanupExpiredDrafts(): int
    {
        $cutoffDate = now()->subDays(self::MAX_DRAFT_AGE_DAYS)->toIso8601String();
        $deletedCount = 0;

        // Minden tablo_pending média lekérése
        $expiredMedia = Media::where('collection_name', 'tablo_pending')
            ->get()
            ->filter(function (Media $media) use ($cutoffDate) {
                $draftCreatedAt = $media->getCustomProperty('draft_created_at')
                    ?? $media->getCustomProperty('uploaded_at');

                return $draftCreatedAt && $draftCreatedAt < $cutoffDate;
            });

        foreach ($expiredMedia as $media) {
            Log::info('PartnerDraft: Cleaning up expired draft photo', [
                'media_id' => $media->id,
                'draft_id' => $media->getCustomProperty('draft_session_id'),
                'created_at' => $media->getCustomProperty('draft_created_at'),
            ]);

            $media->delete();
            $deletedCount++;
        }

        Log::info('PartnerDraft: Expired drafts cleanup completed', [
            'deleted_count' => $deletedCount,
        ]);

        return $deletedCount;
    }
}

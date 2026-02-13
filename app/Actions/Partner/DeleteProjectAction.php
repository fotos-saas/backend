<?php

declare(strict_types=1);

namespace App\Actions\Partner;

use App\Models\TabloDiscussion;
use App\Models\TabloGallery;
use App\Models\TabloNewsfeedMedia;
use App\Models\TabloNewsfeedPost;
use App\Models\TabloPoll;
use App\Models\TabloPollMedia;
use App\Models\TabloPostMedia;
use App\Models\TabloProject;
use App\Models\TabloSamplePackage;
use App\Models\TabloSamplePackageVersion;
use App\Services\Storage\StorageUsageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Projekt törlése az összes kapcsolódó adat és fizikai fájl tisztításával.
 *
 * A DB cascadeOnDelete automatikusan törli a child rekordokat,
 * DE nem triggereli az Eloquent model event-eket, ezért a fizikai
 * fájlokat (Spatie media + egyedi media modellek) kézzel kell törölni.
 */
class DeleteProjectAction
{
    public function __construct(
        private readonly StorageUsageService $storageService,
    ) {}

    public function execute(TabloProject $project): void
    {
        $partnerId = $project->partner_id;

        DB::beginTransaction();

        try {
            // 1. Egyedi media fájlok törlése (DB cascade nem triggereli a model boot-ot)
            $this->deleteNewsfeedMediaFiles($project);
            $this->deleteDiscussionPostMediaFiles($project);
            $this->deletePollMediaFiles($project);

            // 2. SamplePackageVersion Spatie media törlése
            $this->deleteSamplePackageVersionMedia($project);

            // 3. Gallery törlése (belongsTo - nem cascade!)
            $this->deleteGallery($project);

            // 4. Projekt törlése (Spatie auto-törli a projekt saját media kollekcióit)
            $project->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Projekt törlés hiba', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // Storage újraszámolás (commit után)
        $this->recalculateStorage($partnerId);
    }

    private function deleteNewsfeedMediaFiles(TabloProject $project): void
    {
        $postIds = TabloNewsfeedPost::where('tablo_project_id', $project->id)->pluck('id');
        if ($postIds->isEmpty()) {
            return;
        }

        TabloNewsfeedMedia::whereIn('tablo_newsfeed_post_id', $postIds)
            ->each(fn (TabloNewsfeedMedia $m) => $m->delete());
    }

    private function deleteDiscussionPostMediaFiles(TabloProject $project): void
    {
        $discussionIds = TabloDiscussion::where('tablo_project_id', $project->id)->pluck('id');
        if ($discussionIds->isEmpty()) {
            return;
        }

        $postIds = DB::table('tablo_discussion_posts')
            ->whereIn('tablo_discussion_id', $discussionIds)
            ->pluck('id');
        if ($postIds->isEmpty()) {
            return;
        }

        TabloPostMedia::whereIn('tablo_discussion_post_id', $postIds)
            ->each(fn (TabloPostMedia $m) => $m->delete());
    }

    private function deletePollMediaFiles(TabloProject $project): void
    {
        $pollIds = TabloPoll::where('tablo_project_id', $project->id)->pluck('id');
        if ($pollIds->isEmpty()) {
            return;
        }

        TabloPollMedia::whereIn('tablo_poll_id', $pollIds)
            ->each(fn (TabloPollMedia $m) => $m->delete());
    }

    private function deleteSamplePackageVersionMedia(TabloProject $project): void
    {
        $packageIds = TabloSamplePackage::where('tablo_project_id', $project->id)->pluck('id');
        if ($packageIds->isEmpty()) {
            return;
        }

        TabloSamplePackageVersion::whereIn('package_id', $packageIds)
            ->each(fn (TabloSamplePackageVersion $v) => $v->clearMediaCollection('sample_image'));
    }

    private function deleteGallery(TabloProject $project): void
    {
        if (! $project->tablo_gallery_id) {
            return;
        }

        $gallery = TabloGallery::find($project->tablo_gallery_id);
        if (! $gallery) {
            return;
        }

        // Más projekt is használja?
        $otherCount = TabloProject::where('tablo_gallery_id', $gallery->id)
            ->where('id', '!=', $project->id)
            ->count();

        // FK nullázás, mert a gallery törlés előtt kell
        $project->update(['tablo_gallery_id' => null]);

        if ($otherCount === 0) {
            $gallery->clearMediaCollection('photos');
            $gallery->delete();
        }
    }

    private function recalculateStorage(int $partnerId): void
    {
        try {
            $partner = \App\Models\TabloPartner::find($partnerId);
            if ($partner?->user?->partner) {
                $this->storageService->recalculateAndCache($partner->user->partner);
            }
        } catch (\Throwable $e) {
            Log::warning('Storage újraszámolás hiba projekt törlés után', [
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

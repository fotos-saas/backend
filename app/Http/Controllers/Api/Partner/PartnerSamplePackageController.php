<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Requests\Api\Partner\StoreSamplePackageRequest;
use App\Http\Requests\Api\Partner\StoreSampleVersionRequest;
use App\Models\TabloSamplePackage;
use App\Models\TabloSamplePackageVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerSamplePackageController extends Controller
{
    use PartnerAuthTrait;

    /**
     * Csomagok listázása verzióikkal
     */
    public function index(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $packages = $project->samplePackages()
            ->ordered()
            ->with(['versions' => fn ($q) => $q->orderByDesc('version_number')])
            ->withCount('versions')
            ->get();

        $data = $packages->map(fn (TabloSamplePackage $pkg) => [
            'id' => $pkg->id,
            'title' => $pkg->title,
            'sortOrder' => $pkg->sort_order,
            'isActive' => $pkg->is_active,
            'versionsCount' => $pkg->versions_count,
            'versions' => $pkg->versions->map(fn (TabloSamplePackageVersion $v) => [
                'id' => $v->id,
                'versionNumber' => $v->version_number,
                'description' => $v->description,
                'imageUrl' => $v->image_url,
                'thumbnailUrl' => $v->thumbnail_url,
                'createdAt' => $v->created_at->toIso8601String(),
            ]),
            'createdAt' => $pkg->created_at->toIso8601String(),
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * Új csomag létrehozása
     */
    public function store(StoreSamplePackageRequest $request, int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $maxSort = $project->samplePackages()->max('sort_order') ?? 0;

        $package = $project->samplePackages()->create([
            'title' => $request->validated('title'),
            'sort_order' => $maxSort + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Minta csomag létrehozva.',
            'data' => [
                'id' => $package->id,
                'title' => $package->title,
                'sortOrder' => $package->sort_order,
                'isActive' => $package->is_active,
                'versionsCount' => 0,
                'versions' => [],
                'createdAt' => $package->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Csomag módosítása
     */
    public function update(StoreSamplePackageRequest $request, int $projectId, int $packageId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $package = TabloSamplePackage::where('id', $packageId)
            ->where('tablo_project_id', $project->id)
            ->firstOrFail();

        $package->update(['title' => $request->validated('title')]);

        return response()->json([
            'success' => true,
            'message' => 'Minta csomag módosítva.',
            'data' => [
                'id' => $package->id,
                'title' => $package->title,
            ],
        ]);
    }

    /**
     * Csomag törlése (cascade törli a verziókat is)
     */
    public function destroy(int $projectId, int $packageId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $package = TabloSamplePackage::where('id', $packageId)
            ->where('tablo_project_id', $project->id)
            ->firstOrFail();

        // Médiafájlok törlése a verziókból
        foreach ($package->versions as $version) {
            $version->clearMediaCollection('sample_image');
        }

        $package->delete();

        return response()->json([
            'success' => true,
            'message' => 'Minta csomag törölve.',
        ]);
    }

    /**
     * Új verzió hozzáadása csomaghoz
     */
    public function storeVersion(StoreSampleVersionRequest $request, int $projectId, int $packageId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $package = TabloSamplePackage::where('id', $packageId)
            ->where('tablo_project_id', $project->id)
            ->firstOrFail();

        $version = $package->versions()->create([
            'version_number' => $package->getNextVersionNumber(),
            'description' => $request->validated('description'),
        ]);

        $version->addMediaFromRequest('image')
            ->toMediaCollection('sample_image');

        return response()->json([
            'success' => true,
            'message' => 'Új verzió hozzáadva.',
            'data' => [
                'id' => $version->id,
                'versionNumber' => $version->version_number,
                'description' => $version->description,
                'imageUrl' => $version->image_url,
                'thumbnailUrl' => $version->thumbnail_url,
                'createdAt' => $version->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Verzió módosítása
     */
    public function updateVersion(Request $request, int $projectId, int $packageId, int $versionId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $package = TabloSamplePackage::where('id', $packageId)
            ->where('tablo_project_id', $project->id)
            ->firstOrFail();

        $version = TabloSamplePackageVersion::where('id', $versionId)
            ->where('package_id', $package->id)
            ->firstOrFail();

        $validated = $request->validate([
            'description' => ['sometimes', 'string', 'max:2000'],
            'image' => ['sometimes', 'image', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp'],
        ]);

        if (isset($validated['description'])) {
            $version->update(['description' => $validated['description']]);
        }

        if ($request->hasFile('image')) {
            $version->clearMediaCollection('sample_image');
            $version->addMediaFromRequest('image')
                ->toMediaCollection('sample_image');
        }

        $version->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Verzió módosítva.',
            'data' => [
                'id' => $version->id,
                'versionNumber' => $version->version_number,
                'description' => $version->description,
                'imageUrl' => $version->image_url,
                'thumbnailUrl' => $version->thumbnail_url,
                'createdAt' => $version->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Verzió törlése
     */
    public function destroyVersion(int $projectId, int $packageId, int $versionId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $package = TabloSamplePackage::where('id', $packageId)
            ->where('tablo_project_id', $project->id)
            ->firstOrFail();

        $version = TabloSamplePackageVersion::where('id', $versionId)
            ->where('package_id', $package->id)
            ->firstOrFail();

        $version->clearMediaCollection('sample_image');
        $version->delete();

        return response()->json([
            'success' => true,
            'message' => 'Verzió törölve.',
        ]);
    }
}

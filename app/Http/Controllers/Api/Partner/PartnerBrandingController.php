<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\UpdateBrandingRequest;
use App\Models\PartnerBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PartnerBrandingController
 *
 * Partner márkajelzés beállítások kezelése:
 * - Branding adatok lekérdezése
 * - Márkanév + aktív státusz frissítése
 * - Logo/Favicon/OG kép feltöltés és törlés
 */
class PartnerBrandingController extends Controller
{
    /**
     * GET /api/partner/branding
     */
    public function show(Request $request): JsonResponse
    {
        $partner = $request->user()->partner;

        if (! $partner) {
            return response()->json(['message' => 'Partner fiók nem található.'], 404);
        }

        $branding = $partner->branding;

        return response()->json([
            'branding' => $branding ? [
                'brand_name' => $branding->brand_name,
                'is_active' => $branding->is_active,
                'logo_url' => $branding->getLogoUrl(),
                'favicon_url' => $branding->getFaviconUrl(),
                'og_image_url' => $branding->getOgImageUrl(),
            ] : null,
        ]);
    }

    /**
     * POST /api/partner/branding
     */
    public function update(UpdateBrandingRequest $request): JsonResponse
    {
        $partner = $request->user()->partner;

        if (! $partner) {
            return response()->json(['message' => 'Partner fiók nem található.'], 404);
        }

        $branding = PartnerBranding::updateOrCreate(
            ['partner_id' => $partner->id],
            $request->validated()
        );

        return response()->json([
            'message' => 'Márkajelzés sikeresen frissítve.',
            'branding' => [
                'brand_name' => $branding->brand_name,
                'is_active' => $branding->is_active,
                'logo_url' => $branding->getLogoUrl(),
                'favicon_url' => $branding->getFaviconUrl(),
                'og_image_url' => $branding->getOgImageUrl(),
            ],
        ]);
    }

    /**
     * POST /api/partner/branding/logo
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'max:2048'],
        ]);

        $branding = $this->getOrCreateBranding($request);

        $branding->addMediaFromRequest('logo')
            ->toMediaCollection('brand_logo');

        return response()->json([
            'message' => 'Logó sikeresen feltöltve.',
            'logo_url' => $branding->fresh()->getLogoUrl(),
        ]);
    }

    /**
     * POST /api/partner/branding/favicon
     */
    public function uploadFavicon(Request $request): JsonResponse
    {
        $request->validate([
            'favicon' => ['required', 'image', 'max:512'],
        ]);

        $branding = $this->getOrCreateBranding($request);

        $branding->addMediaFromRequest('favicon')
            ->toMediaCollection('brand_favicon');

        return response()->json([
            'message' => 'Favicon sikeresen feltöltve.',
            'favicon_url' => $branding->fresh()->getFaviconUrl(),
        ]);
    }

    /**
     * POST /api/partner/branding/og-image
     */
    public function uploadOgImage(Request $request): JsonResponse
    {
        $request->validate([
            'og_image' => ['required', 'image', 'max:5120'],
        ]);

        $branding = $this->getOrCreateBranding($request);

        $branding->addMediaFromRequest('og_image')
            ->toMediaCollection('brand_og_image');

        return response()->json([
            'message' => 'OG kép sikeresen feltöltve.',
            'og_image_url' => $branding->fresh()->getOgImageUrl(),
        ]);
    }

    /**
     * DELETE /api/partner/branding/logo
     */
    public function deleteLogo(Request $request): JsonResponse
    {
        $branding = $request->user()->partner?->branding;

        if ($branding) {
            $branding->clearMediaCollection('brand_logo');
        }

        return response()->json(['message' => 'Logó törölve.']);
    }

    /**
     * DELETE /api/partner/branding/favicon
     */
    public function deleteFavicon(Request $request): JsonResponse
    {
        $branding = $request->user()->partner?->branding;

        if ($branding) {
            $branding->clearMediaCollection('brand_favicon');
        }

        return response()->json(['message' => 'Favicon törölve.']);
    }

    /**
     * DELETE /api/partner/branding/og-image
     */
    public function deleteOgImage(Request $request): JsonResponse
    {
        $branding = $request->user()->partner?->branding;

        if ($branding) {
            $branding->clearMediaCollection('brand_og_image');
        }

        return response()->json(['message' => 'OG kép törölve.']);
    }

    private function getOrCreateBranding(Request $request): PartnerBranding
    {
        $partner = $request->user()->partner;

        return PartnerBranding::firstOrCreate(
            ['partner_id' => $partner->id],
            ['brand_name' => null, 'is_active' => false]
        );
    }
}

<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Api\Concerns\ResolvesPartner;
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
    use ResolvesPartner;

    /**
     * GET /api/partner/branding
     *
     * Csapattagok (designer, marketer, stb.) is lekérhetik a branding adatokat.
     * A partner feloldása: saját Partner → csapattag fallback (TabloPartner → partner_id FK → Partner).
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $partner = $this->resolvePartner($user->id);

        // Ha nem találtuk ResolvesPartner-rel (email mismatch), próbáljuk az új FK-n keresztül
        if (! $partner && $user->tablo_partner_id) {
            $tabloPartner = \App\Models\TabloPartner::find($user->tablo_partner_id);
            $partner = $tabloPartner?->subscriptionPartner;
        }

        if (! $partner) {
            return response()->json(['branding' => null]);
        }

        $branding = $partner->branding;

        return response()->json([
            'branding' => $branding ? [
                'brand_name' => $branding->brand_name,
                'is_active' => $branding->is_active,
                'hide_brand_name' => $branding->hide_brand_name,
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
                'hide_brand_name' => $branding->hide_brand_name,
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
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,svg,svgz', 'max:2048'],
        ]);

        $this->validateSvgSafety($request, 'logo');
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
            'favicon' => ['required', 'file', 'mimes:png,jpg,jpeg,svg,svgz', 'max:512'],
        ]);

        $this->validateSvgSafety($request, 'favicon');
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
            'og_image' => ['required', 'file', 'mimes:png,jpg,jpeg,svg,svgz', 'max:5120'],
        ]);

        $this->validateSvgSafety($request, 'og_image');
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

    private function validateSvgSafety(Request $request, string $field): void
    {
        $file = $request->file($field);
        if (! $file || ! in_array($file->getClientOriginalExtension(), ['svg', 'svgz'])) {
            return;
        }

        $content = file_get_contents($file->getRealPath());
        $dangerous = preg_match('/<script[\s>]|on\w+\s*=|javascript:|data:\s*text/i', $content);

        if ($dangerous) {
            abort(422, 'Az SVG fájl nem biztonságos tartalmat tartalmaz.');
        }
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

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Actions\Webshop\GenerateWebshopTokenAction;
use App\Actions\Webshop\InitializeWebshopAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Requests\Api\Partner\GenerateWebshopTokenRequest;
use App\Http\Requests\Api\Partner\UpdateWebshopSettingsRequest;
use App\Models\PartnerAlbum;
use App\Models\ShopSetting;
use App\Models\TabloGallery;
use Illuminate\Http\JsonResponse;

class PartnerWebshopSettingsController extends Controller
{
    use PartnerAuthTrait;

    public function __construct(
        private readonly InitializeWebshopAction $initializeAction,
        private readonly GenerateWebshopTokenAction $tokenAction,
    ) {}

    public function getSettings(): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $settings = ShopSetting::where('tablo_partner_id', $partnerId)->first();

        return $this->successResponse([
            'settings' => $settings ? $this->formatSettings($settings) : null,
        ]);
    }

    public function updateSettings(UpdateWebshopSettingsRequest $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $settings = ShopSetting::where('tablo_partner_id', $partnerId)->first();

        if (!$settings) {
            $settings = $this->initializeAction->execute($partnerId);
        }

        $settings->update($request->validated());

        return $this->successResponse([
            'settings' => $this->formatSettings($settings->fresh()),
            'message' => 'Webshop beállítások frissítve.',
        ]);
    }

    public function initializeWebshop(): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $existing = ShopSetting::where('tablo_partner_id', $partnerId)->first();
        if ($existing) {
            return $this->successResponse([
                'settings' => $this->formatSettings($existing),
                'message' => 'Webshop már inicializálva van.',
            ]);
        }

        $settings = $this->initializeAction->execute($partnerId);

        return $this->successResponse([
            'settings' => $this->formatSettings($settings),
            'message' => 'Webshop inicializálva alapértelmezett beállításokkal.',
        ], 201);
    }

    public function generateToken(GenerateWebshopTokenRequest $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $token = $this->tokenAction->execute(
            $partnerId,
            $request->validated('album_id') ? (int) $request->validated('album_id') : null,
            $request->validated('gallery_id') ? (int) $request->validated('gallery_id') : null,
        );

        return $this->successResponse([
            'token' => $token,
            'message' => 'Webshop link generálva.',
        ]);
    }

    public function getWebshopStatus(): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $settings = ShopSetting::where('tablo_partner_id', $partnerId)->first();

        return $this->successResponse([
            'is_enabled' => $settings?->is_enabled ?? false,
            'is_initialized' => $settings !== null,
        ]);
    }

    public function getAlbumToken(int $albumId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $album = PartnerAlbum::byPartner($partnerId)->findOrFail($albumId);

        return $this->successResponse([
            'token' => $album->webshop_share_token,
        ]);
    }

    public function getGalleryToken(int $galleryId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $gallery = TabloGallery::whereHas('projects', function ($q) use ($partnerId) {
            $q->where('tablo_partner_id', $partnerId);
        })->findOrFail($galleryId);

        return $this->successResponse([
            'token' => $gallery->webshop_share_token,
        ]);
    }

    private function formatSettings(ShopSetting $settings): array
    {
        return [
            'id' => $settings->id,
            'is_enabled' => $settings->is_enabled,
            'welcome_message' => $settings->welcome_message,
            'min_order_amount_huf' => $settings->min_order_amount_huf,
            'shipping_cost_huf' => $settings->shipping_cost_huf,
            'shipping_free_threshold_huf' => $settings->shipping_free_threshold_huf,
            'allow_pickup' => $settings->allow_pickup,
            'allow_shipping' => $settings->allow_shipping,
            'terms_text' => $settings->terms_text,
        ];
    }
}

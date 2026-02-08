<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Actions\Webshop\GenerateWebshopTokenAction;
use App\Actions\Webshop\InitializeWebshopAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Requests\Api\Partner\CreatePaperSizeRequest;
use App\Http\Requests\Api\Partner\CreatePaperTypeRequest;
use App\Http\Requests\Api\Partner\UpdateWebshopSettingsRequest;
use App\Models\PartnerAlbum;
use App\Models\ShopPaperSize;
use App\Models\ShopPaperType;
use App\Models\ShopSetting;
use App\Models\TabloGallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerWebshopSettingsController extends Controller
{
    use PartnerAuthTrait;

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
            $action = new InitializeWebshopAction();
            $settings = $action->execute($partnerId);
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

        $action = new InitializeWebshopAction();
        $settings = $action->execute($partnerId);

        return $this->successResponse([
            'settings' => $this->formatSettings($settings),
            'message' => 'Webshop inicializálva alapértelmezett beállításokkal.',
        ], 201);
    }

    // Paper Sizes

    public function getPaperSizes(): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $sizes = ShopPaperSize::byPartner($partnerId)->ordered()->get();

        return $this->successResponse([
            'paper_sizes' => $sizes->map(fn (ShopPaperSize $s) => $this->formatPaperSize($s)),
        ]);
    }

    public function createPaperSize(CreatePaperSizeRequest $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $size = ShopPaperSize::create([
            'tablo_partner_id' => $partnerId,
            ...$request->validated(),
        ]);

        return $this->successResponse([
            'paper_size' => $this->formatPaperSize($size),
        ], 201);
    }

    public function updatePaperSize(CreatePaperSizeRequest $request, int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $size = ShopPaperSize::byPartner($partnerId)->findOrFail($id);
        $size->update($request->validated());

        return $this->successResponse([
            'paper_size' => $this->formatPaperSize($size->fresh()),
        ]);
    }

    public function deletePaperSize(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $size = ShopPaperSize::byPartner($partnerId)->findOrFail($id);

        if ($size->products()->whereHas('orderItems')->exists()) {
            return $this->errorResponse('Nem törölhető: vannak hozzá tartozó rendelések.', 422);
        }

        $size->products()->delete();
        $size->delete();

        return $this->successResponse(['message' => 'Papírméret törölve.']);
    }

    // Paper Types

    public function getPaperTypes(): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $types = ShopPaperType::byPartner($partnerId)->ordered()->get();

        return $this->successResponse([
            'paper_types' => $types->map(fn (ShopPaperType $t) => $this->formatPaperType($t)),
        ]);
    }

    public function createPaperType(CreatePaperTypeRequest $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $type = ShopPaperType::create([
            'tablo_partner_id' => $partnerId,
            ...$request->validated(),
        ]);

        return $this->successResponse([
            'paper_type' => $this->formatPaperType($type),
        ], 201);
    }

    public function updatePaperType(CreatePaperTypeRequest $request, int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $type = ShopPaperType::byPartner($partnerId)->findOrFail($id);
        $type->update($request->validated());

        return $this->successResponse([
            'paper_type' => $this->formatPaperType($type->fresh()),
        ]);
    }

    public function deletePaperType(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $type = ShopPaperType::byPartner($partnerId)->findOrFail($id);

        if ($type->products()->whereHas('orderItems')->exists()) {
            return $this->errorResponse('Nem törölhető: vannak hozzá tartozó rendelések.', 422);
        }

        $type->products()->delete();
        $type->delete();

        return $this->successResponse(['message' => 'Papírtípus törölve.']);
    }

    // Token generálás

    public function generateToken(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $request->validate([
            'album_id' => 'nullable|integer',
            'gallery_id' => 'nullable|integer',
        ]);

        if (!$request->input('album_id') && !$request->input('gallery_id')) {
            return $this->errorResponse('album_id vagy gallery_id kötelező.', 422);
        }

        $action = new GenerateWebshopTokenAction();
        $token = $action->execute(
            $partnerId,
            $request->input('album_id') ? (int) $request->input('album_id') : null,
            $request->input('gallery_id') ? (int) $request->input('gallery_id') : null,
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

    // Formatters

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

    private function formatPaperSize(ShopPaperSize $size): array
    {
        return [
            'id' => $size->id,
            'name' => $size->name,
            'width_cm' => (float) $size->width_cm,
            'height_cm' => (float) $size->height_cm,
            'display_order' => $size->display_order,
            'is_active' => $size->is_active,
        ];
    }

    private function formatPaperType(ShopPaperType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'description' => $type->description,
            'display_order' => $type->display_order,
            'is_active' => $type->is_active,
        ];
    }
}

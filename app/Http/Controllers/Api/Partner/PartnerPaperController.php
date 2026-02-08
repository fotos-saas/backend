<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Requests\Api\Partner\CreatePaperSizeRequest;
use App\Http\Requests\Api\Partner\CreatePaperTypeRequest;
use App\Models\ShopPaperSize;
use App\Models\ShopPaperType;
use Illuminate\Http\JsonResponse;

class PartnerPaperController extends Controller
{
    use PartnerAuthTrait;

    // ============ Paper Sizes ============

    public function getSizes(): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $sizes = ShopPaperSize::byPartner($partnerId)->ordered()->get();

        return $this->successResponse([
            'paper_sizes' => $sizes->map(fn (ShopPaperSize $s) => $this->formatSize($s)),
        ]);
    }

    public function createSize(CreatePaperSizeRequest $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $size = ShopPaperSize::create([
            'tablo_partner_id' => $partnerId,
            ...$request->validated(),
        ]);

        return $this->successResponse([
            'paper_size' => $this->formatSize($size),
        ], 201);
    }

    public function updateSize(CreatePaperSizeRequest $request, int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $size = ShopPaperSize::byPartner($partnerId)->findOrFail($id);
        $size->update($request->validated());

        return $this->successResponse([
            'paper_size' => $this->formatSize($size->fresh()),
        ]);
    }

    public function deleteSize(int $id): JsonResponse
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

    // ============ Paper Types ============

    public function getTypes(): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $types = ShopPaperType::byPartner($partnerId)->ordered()->get();

        return $this->successResponse([
            'paper_types' => $types->map(fn (ShopPaperType $t) => $this->formatType($t)),
        ]);
    }

    public function createType(CreatePaperTypeRequest $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $type = ShopPaperType::create([
            'tablo_partner_id' => $partnerId,
            ...$request->validated(),
        ]);

        return $this->successResponse([
            'paper_type' => $this->formatType($type),
        ], 201);
    }

    public function updateType(CreatePaperTypeRequest $request, int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $type = ShopPaperType::byPartner($partnerId)->findOrFail($id);
        $type->update($request->validated());

        return $this->successResponse([
            'paper_type' => $this->formatType($type->fresh()),
        ]);
    }

    public function deleteType(int $id): JsonResponse
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

    // ============ Formatters ============

    private function formatSize(ShopPaperSize $size): array
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

    private function formatType(ShopPaperType $type): array
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

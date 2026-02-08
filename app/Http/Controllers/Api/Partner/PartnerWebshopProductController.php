<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Requests\Api\Partner\BulkUpdatePricingRequest;
use App\Models\ShopPaperSize;
use App\Models\ShopPaperType;
use App\Models\ShopProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerWebshopProductController extends Controller
{
    use PartnerAuthTrait;

    public function getProducts(): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $products = ShopProduct::byPartner($partnerId)
            ->with(['paperSize', 'paperType'])
            ->get();

        $paperSizes = ShopPaperSize::byPartner($partnerId)->ordered()->get();
        $paperTypes = ShopPaperType::byPartner($partnerId)->ordered()->get();

        // Ha nincs product kombináció, generáljuk
        if ($products->isEmpty() && $paperSizes->isNotEmpty() && $paperTypes->isNotEmpty()) {
            $this->syncProducts($partnerId, $paperSizes, $paperTypes);
            $products = ShopProduct::byPartner($partnerId)->with(['paperSize', 'paperType'])->get();
        }

        return $this->successResponse([
            'products' => $products->map(fn (ShopProduct $p) => $this->formatProduct($p)),
            'paper_sizes' => $paperSizes->map(fn (ShopPaperSize $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'width_cm' => (float) $s->width_cm,
                'height_cm' => (float) $s->height_cm,
                'is_active' => $s->is_active,
            ]),
            'paper_types' => $paperTypes->map(fn (ShopPaperType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'is_active' => $t->is_active,
            ]),
        ]);
    }

    public function bulkUpdatePricing(BulkUpdatePricingRequest $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $updates = $request->validated()['products'];
        $productIds = array_map('intval', array_column($updates, 'id'));

        // Partner saját termékei-e
        $validProducts = ShopProduct::byPartner($partnerId)
            ->whereIn('id', $productIds)
            ->pluck('id')
            ->toArray();

        foreach ($updates as $update) {
            $id = (int) $update['id'];
            if (!in_array($id, $validProducts)) {
                continue;
            }

            ShopProduct::where('id', $id)->update([
                'price_huf' => (int) $update['price_huf'],
                'is_active' => (bool) $update['is_active'],
            ]);
        }

        return $this->successResponse(['message' => 'Árak frissítve.']);
    }

    public function toggleProductStatus(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $product = ShopProduct::byPartner($partnerId)->findOrFail($id);
        $product->update(['is_active' => !$product->is_active]);

        return $this->successResponse([
            'product' => $this->formatProduct($product->fresh()->load(['paperSize', 'paperType'])),
        ]);
    }

    private function syncProducts(int $partnerId, $sizes, $types): void
    {
        foreach ($sizes as $size) {
            foreach ($types as $type) {
                ShopProduct::firstOrCreate([
                    'tablo_partner_id' => $partnerId,
                    'shop_paper_size_id' => $size->id,
                    'shop_paper_type_id' => $type->id,
                ], [
                    'price_huf' => 0,
                    'is_active' => true,
                ]);
            }
        }
    }

    private function formatProduct(ShopProduct $product): array
    {
        return [
            'id' => $product->id,
            'paper_size_id' => $product->shop_paper_size_id,
            'paper_size_name' => $product->paperSize->name ?? '',
            'paper_type_id' => $product->shop_paper_type_id,
            'paper_type_name' => $product->paperType->name ?? '',
            'price_huf' => $product->price_huf,
            'is_active' => $product->is_active,
        ];
    }
}

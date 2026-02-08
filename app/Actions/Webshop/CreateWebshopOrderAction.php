<?php

declare(strict_types=1);

namespace App\Actions\Webshop;

use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopProduct;
use App\Models\ShopSetting;
use Illuminate\Support\Facades\DB;

class CreateWebshopOrderAction
{
    public function execute(array $source, array $data): ShopOrder
    {
        $partnerId = $source['partner_id'];
        $settings = ShopSetting::where('tablo_partner_id', $partnerId)->firstOrFail();

        // Elérhető média ID-k lekérése a source-ból (IDOR védelem)
        $validMediaIds = $this->getValidMediaIds($source);

        // Tételek és árak validálása
        $items = $data['items'];
        $subtotal = 0;
        $validatedItems = [];

        foreach ($items as $item) {
            $product = ShopProduct::byPartner($partnerId)
                ->active()
                ->where('id', (int) $item['product_id'])
                ->with(['paperSize', 'paperType'])
                ->firstOrFail();

            $mediaId = (int) $item['media_id'];

            // Media ID validáció: csak a source-hoz tartozó médiák engedélyezettek
            if (!in_array($mediaId, $validMediaIds, true)) {
                throw new \InvalidArgumentException('Érvénytelen média hivatkozás.');
            }

            $quantity = (int) $item['quantity'];
            $itemSubtotal = $product->price_huf * $quantity;
            $subtotal += $itemSubtotal;

            $validatedItems[] = [
                'product' => $product,
                'media_id' => $mediaId,
                'quantity' => $quantity,
                'subtotal' => $itemSubtotal,
            ];
        }

        // Szállítás költség
        $shippingCost = 0;
        if ($data['delivery_method'] === 'shipping') {
            $shippingCost = $settings->shipping_cost_huf;
            if ($settings->shipping_free_threshold_huf && $subtotal >= $settings->shipping_free_threshold_huf) {
                $shippingCost = 0;
            }
        }

        $total = $subtotal + $shippingCost;

        // Minimum összeg ellenőrzés
        if ($settings->min_order_amount_huf > 0 && $subtotal < $settings->min_order_amount_huf) {
            throw new \InvalidArgumentException(
                "A minimum rendelési összeg {$settings->min_order_amount_huf} Ft."
            );
        }

        return DB::transaction(function () use ($partnerId, $source, $data, $subtotal, $shippingCost, $total, $validatedItems) {
            // Rendelés létrehozása
            $order = ShopOrder::create([
                'order_number' => ShopOrder::generateOrderNumber(),
                'tablo_partner_id' => $partnerId,
                'partner_album_id' => $source['album_id'],
                'tablo_gallery_id' => $source['gallery_id'],
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'subtotal_huf' => $subtotal,
                'shipping_cost_huf' => $shippingCost,
                'total_huf' => $total,
                'status' => ShopOrder::STATUS_PENDING,
                'delivery_method' => $data['delivery_method'],
                'shipping_address' => $data['shipping_address'] ?? null,
                'shipping_notes' => $data['shipping_notes'] ?? null,
                'customer_notes' => $data['customer_notes'] ?? null,
            ]);

            // Tételek létrehozása snapshot-tal
            foreach ($validatedItems as $vi) {
                ShopOrderItem::create([
                    'shop_order_id' => $order->id,
                    'shop_product_id' => $vi['product']->id,
                    'media_id' => $vi['media_id'],
                    'paper_size_name' => $vi['product']->paperSize->name,
                    'paper_type_name' => $vi['product']->paperType->name,
                    'unit_price_huf' => $vi['product']->price_huf,
                    'quantity' => $vi['quantity'],
                    'subtotal_huf' => $vi['subtotal'],
                ]);
            }

            return $order;
        });
    }

    private function getValidMediaIds(array $source): array
    {
        $model = $source['model'];

        if ($source['type'] === 'album') {
            return $model->getMedia('photos')->pluck('id')->toArray();
        }

        if ($source['type'] === 'gallery') {
            return $model->getMedia('photos')->pluck('id')->toArray();
        }

        return [];
    }
}

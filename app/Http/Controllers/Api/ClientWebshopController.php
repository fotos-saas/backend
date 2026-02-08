<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Actions\Webshop\CreateWebshopOrderAction;
use App\Actions\Webshop\CreateStripeCheckoutAction;
use App\Models\PartnerAlbum;
use App\Models\ShopProduct;
use App\Models\ShopSetting;
use App\Models\TabloGallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientWebshopController extends Controller
{
    public function getShopConfig(string $token): JsonResponse
    {
        $source = $this->resolveSource($token);
        if (!$source) {
            return $this->errorResponse('Érvénytelen link.', 404);
        }

        $partnerId = $source['partner_id'];
        $settings = ShopSetting::where('tablo_partner_id', $partnerId)->first();

        if (!$settings || !$settings->is_enabled) {
            return $this->errorResponse('A webshop nem elérhető.', 403);
        }

        return $this->successResponse([
            'config' => [
                'welcome_message' => $settings->welcome_message,
                'min_order_amount_huf' => $settings->min_order_amount_huf,
                'shipping_cost_huf' => $settings->shipping_cost_huf,
                'shipping_free_threshold_huf' => $settings->shipping_free_threshold_huf,
                'allow_pickup' => $settings->allow_pickup,
                'allow_shipping' => $settings->allow_shipping,
                'terms_text' => $settings->terms_text,
            ],
            'source_type' => $source['type'],
            'source_name' => $source['name'],
        ]);
    }

    public function getProducts(string $token): JsonResponse
    {
        $source = $this->resolveSource($token);
        if (!$source) {
            return $this->errorResponse('Érvénytelen link.', 404);
        }

        $products = ShopProduct::byPartner($source['partner_id'])
            ->active()
            ->where('price_huf', '>', 0)
            ->with(['paperSize', 'paperType'])
            ->get()
            ->filter(fn ($p) => $p->paperSize && $p->paperSize->is_active && $p->paperType && $p->paperType->is_active);

        return $this->successResponse([
            'products' => $products->values()->map(fn (ShopProduct $p) => [
                'id' => $p->id,
                'paper_size_name' => $p->paperSize->name,
                'paper_type_name' => $p->paperType->name,
                'width_cm' => (float) $p->paperSize->width_cm,
                'height_cm' => (float) $p->paperSize->height_cm,
                'price_huf' => $p->price_huf,
            ]),
        ]);
    }

    public function getPhotos(string $token): JsonResponse
    {
        $source = $this->resolveSource($token);
        if (!$source) {
            return $this->errorResponse('Érvénytelen link.', 404);
        }

        $photos = [];
        if ($source['type'] === 'album' && $source['model'] instanceof PartnerAlbum) {
            $photos = $source['model']->getPhotosWithUrls();
        } elseif ($source['type'] === 'gallery' && $source['model'] instanceof TabloGallery) {
            $photos = $source['model']->getMedia('photos')->map(function ($media) {
                return [
                    'id' => $media->id,
                    'name' => $media->file_name,
                    'title' => $media->getCustomProperty('iptc_title') ?? pathinfo($media->file_name, PATHINFO_FILENAME),
                    'original_url' => $media->getUrl(),
                    'thumb_url' => $media->getUrl('thumb'),
                    'preview_url' => $media->getUrl('preview'),
                ];
            })->toArray();
        }

        return $this->successResponse([
            'photos' => $photos,
        ]);
    }

    public function createCheckout(
        Request $request,
        string $token,
        CreateWebshopOrderAction $orderAction,
        CreateStripeCheckoutAction $checkoutAction,
    ): JsonResponse {
        $source = $this->resolveSource($token);
        if (!$source) {
            return $this->errorResponse('Érvénytelen link.', 404);
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:30',
            'delivery_method' => 'required|in:pickup,shipping',
            'shipping_address' => 'required_if:delivery_method,shipping|nullable|string|max:500',
            'shipping_notes' => 'nullable|string|max:500',
            'customer_notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.media_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1|max:99',
        ]);

        $order = $orderAction->execute($source, $validated);

        $result = $checkoutAction->execute($order, $token);

        if (!$result['success']) {
            return $this->errorResponse($result['error'] ?? 'Fizetés indítása sikertelen.', 422);
        }

        return $this->successResponse([
            'checkout_url' => $result['checkout_url'],
            'order_number' => $order->order_number,
        ]);
    }

    private function resolveSource(string $token): ?array
    {
        // Album keresés
        $album = PartnerAlbum::where('webshop_share_token', $token)->first();
        if ($album) {
            return [
                'type' => 'album',
                'model' => $album,
                'partner_id' => $album->tablo_partner_id,
                'name' => $album->name,
                'album_id' => $album->id,
                'gallery_id' => null,
            ];
        }

        // Galéria keresés
        $gallery = TabloGallery::where('webshop_share_token', $token)->first();
        if ($gallery) {
            $project = $gallery->projects()->first();
            $partnerId = $project?->tablo_partner_id;
            if (!$partnerId) {
                return null;
            }

            return [
                'type' => 'gallery',
                'model' => $gallery,
                'partner_id' => $partnerId,
                'name' => $gallery->name,
                'album_id' => null,
                'gallery_id' => $gallery->id,
            ];
        }

        return null;
    }
}

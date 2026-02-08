<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Helpers\QueryHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Models\ShopOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerWebshopOrderController extends Controller
{
    use PartnerAuthTrait;

    public function index(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $query = ShopOrder::byPartner($partnerId)
            ->withCount('items')
            ->orderByDesc('created_at');

        if ($status = $request->input('status')) {
            $query->withStatus($status);
        }

        if ($search = $request->input('search')) {
            $safe = QueryHelper::safeLikePattern($search);
            $query->where(function ($q) use ($safe) {
                $q->where('order_number', 'ilike', $safe)
                  ->orWhere('customer_name', 'ilike', $safe)
                  ->orWhere('customer_email', 'ilike', $safe);
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 50);
        $orders = $query->paginate($perPage);

        return $this->successResponse([
            'orders' => collect($orders->items())->map(fn (ShopOrder $o) => $this->formatOrder($o)),
            'total' => $orders->total(),
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $order = ShopOrder::byPartner($partnerId)
            ->with(['items.media', 'items.product.paperSize', 'items.product.paperType', 'client', 'guestSession'])
            ->findOrFail($id);

        return $this->successResponse([
            'order' => $this->formatOrderDetail($order),
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $request->validate([
            'status' => 'required|in:processing,shipped,completed,cancelled',
            'tracking_number' => 'nullable|string|max:100',
            'internal_notes' => 'nullable|string|max:2000',
        ]);

        $order = ShopOrder::byPartner($partnerId)->findOrFail($id);

        $data = ['status' => $request->input('status')];

        if ($request->input('status') === ShopOrder::STATUS_SHIPPED) {
            $data['shipped_at'] = now();
            if ($request->filled('tracking_number')) {
                $data['tracking_number'] = $request->input('tracking_number');
            }
        }

        if ($request->filled('internal_notes')) {
            $data['internal_notes'] = $request->input('internal_notes');
        }

        $order->update($data);

        return $this->successResponse([
            'order' => $this->formatOrder($order->fresh()->loadCount('items')),
            'message' => 'Rendelés státusza frissítve.',
        ]);
    }

    public function getStats(): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $query = ShopOrder::byPartner($partnerId);

        $totalOrders = (clone $query)->count();
        $pendingOrders = (clone $query)->withStatus(ShopOrder::STATUS_PAID)->count()
            + (clone $query)->withStatus(ShopOrder::STATUS_PROCESSING)->count();
        $totalRevenue = (clone $query)->paid()->sum('total_huf');
        $thisMonthRevenue = (clone $query)->paid()
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum('total_huf');

        return $this->successResponse([
            'stats' => [
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'total_revenue_huf' => (int) $totalRevenue,
                'this_month_revenue_huf' => (int) $thisMonthRevenue,
            ],
        ]);
    }

    private function formatOrder(ShopOrder $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
            'subtotal_huf' => $order->subtotal_huf,
            'shipping_cost_huf' => $order->shipping_cost_huf,
            'total_huf' => $order->total_huf,
            'status' => $order->status,
            'delivery_method' => $order->delivery_method,
            'items_count' => $order->items_count ?? 0,
            'created_at' => $order->created_at?->toISOString(),
            'paid_at' => $order->paid_at?->toISOString(),
            'shipped_at' => $order->shipped_at?->toISOString(),
        ];
    }

    private function formatOrderDetail(ShopOrder $order): array
    {
        return [
            ...$this->formatOrder($order),
            'shipping_address' => $order->shipping_address,
            'shipping_notes' => $order->shipping_notes,
            'tracking_number' => $order->tracking_number,
            'customer_notes' => $order->customer_notes,
            'internal_notes' => $order->internal_notes,
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'paper_size_name' => $item->paper_size_name,
                    'paper_type_name' => $item->paper_type_name,
                    'unit_price_huf' => $item->unit_price_huf,
                    'quantity' => $item->quantity,
                    'subtotal_huf' => $item->subtotal_huf,
                    'photo_url' => $item->media?->getUrl('preview') ?? '',
                    'photo_filename' => $item->media?->file_name ?? '',
                ];
            }),
        ];
    }
}

<?php

namespace App\Actions\Order;

use App\Events\OrderPlaced;
use App\Http\Requests\Api\Order\StoreOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateOrderAction
{
    public function execute(StoreOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        $isGuest = ! $user;

        try {
            DB::beginTransaction();

            $orderData = [
                'user_id' => $user?->id,
                'work_session_id' => $request->work_session_id,
                'package_id' => $request->package_id,
                'coupon_id' => $request->coupon_id,
                'coupon_discount' => $request->coupon_discount,
                'subtotal_huf' => $request->subtotal_huf,
                'discount_huf' => $request->discount_huf,
                'total_gross_huf' => $request->total_gross_huf,
                'payment_method_id' => $request->payment_method_id,
                'shipping_method_id' => $request->shipping_method_id,
                'package_point_id' => $request->package_point_id,
                'shipping_address' => $request->shipping_address,
                'shipping_cost_huf' => $request->shipping_cost_huf,
                'cod_fee_huf' => $request->cod_fee_huf ?? 0,
                'status' => 'payment_pending',
            ];

            if ($isGuest) {
                $orderData = array_merge($orderData, [
                    'guest_name' => $request->guest_name,
                    'guest_email' => $request->guest_email,
                    'guest_phone' => $request->guest_phone,
                    'guest_address' => $request->guest_address,
                ]);
            }

            if ($request->is_company_purchase) {
                $orderData = array_merge($orderData, [
                    'is_company_purchase' => true,
                    'company_name' => $request->company_name,
                    'tax_number' => $request->tax_number,
                    'billing_address' => $request->billing_address,
                ]);
            }

            $order = Order::create($orderData);

            foreach ($request->items as $itemData) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'photo_id' => $itemData['photo_id'] ?? null,
                    'size' => $itemData['size'],
                    'quantity' => $itemData['quantity'],
                    'unit_price_huf' => $itemData['unit_price_huf'],
                    'total_price_huf' => $itemData['total_price_huf'],
                ]);
            }

            DB::commit();

            $order->load(['items.photo', 'user', 'coupon', 'package', 'workSession', 'paymentMethod', 'shippingMethod', 'packagePoint']);

            event(new OrderPlaced($order));

            Log::info('Order created', [
                'order_id' => $order->id,
                'user_id' => $user?->id,
                'is_guest' => $isGuest,
                'total' => $order->total_gross_huf,
            ]);

            return response()->json($order, 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'user_id' => $user?->id,
            ]);

            return response()->json([
                'message' => 'Order creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

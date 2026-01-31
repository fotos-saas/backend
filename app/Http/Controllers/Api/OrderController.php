<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderPlaced;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\InvoicingService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * StripeService and InvoicingService instances
     */
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly InvoicingService $invoicingService
    ) {}

    /**
     * Get user's orders
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $orders = Order::query()
            ->with(['items.photo', 'coupon', 'package', 'workSession.albums'])
            ->forUser($user->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($orders);
    }

    /**
     * Get order details
     * SECURITY: Only order owner or guest with matching email can view
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        // Authorization check
        if ($order->user_id) {
            // Authenticated order - must be owner or admin
            if (! $user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            if ($user->id !== $order->user_id && ! $user->hasRole('admin')) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        } else {
            // Guest order - verify email
            $guestEmail = $request->query('guest_email') ?? $request->input('guest_email');

            if (! $guestEmail || strtolower($guestEmail) !== strtolower($order->guest_email)) {
                return response()->json([
                    'message' => 'Forbidden. Guest email verification required.',
                ], 403);
            }
        }

        $order->load(['items.photo', 'user', 'coupon', 'package', 'workSession']);

        return response()->json($order);
    }

    /**
     * Create a new order
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $isGuest = ! $user;

        // Validation rules
        $rules = [
            'work_session_id' => 'nullable|exists:work_sessions,id',
            'package_id' => 'nullable|exists:packages,id',
            'coupon_id' => 'nullable|exists:coupons,id',
            'coupon_discount' => 'nullable|integer|min:0',
            'subtotal_huf' => 'required|integer|min:0',
            'discount_huf' => 'required|integer|min:0',
            'total_gross_huf' => 'required|integer|min:175', // Stripe minimum
            'items' => 'required|array|min:1',
            'items.*.photo_id' => 'nullable|exists:photos,id',
            'items.*.size' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price_huf' => 'required|integer|min:0',
            'items.*.total_price_huf' => 'required|integer|min:0',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'shipping_method_id' => 'required|exists:shipping_methods,id',
            'package_point_id' => 'nullable|exists:package_points,id',
            'shipping_address' => 'nullable|string',
            'shipping_cost_huf' => 'required|integer|min:0',
            'cod_fee_huf' => 'nullable|integer|min:0',
        ];

        // Add guest data validation if user is not authenticated
        if ($isGuest) {
            $rules = array_merge($rules, [
                'guest_name' => 'required|string|max:255',
                'guest_email' => 'required|email|max:255',
                'guest_phone' => 'required|string|max:20',
                'guest_address' => 'required|string',
            ]);
        }

        // Company purchase fields (optional for both guest and registered)
        $rules = array_merge($rules, [
            'is_company_purchase' => 'nullable|boolean',
            'company_name' => 'nullable|required_if:is_company_purchase,true|string|max:255',
            'tax_number' => 'nullable|required_if:is_company_purchase,true|string|max:50',
            'billing_address' => 'nullable|string',
        ]);

        $validator = Validator::make($request->all(), $rules, [
            'total_gross_huf.min' => 'A végösszeg legalább 175 Ft kell legyen. Kérlek adj hozzá több tételt vagy módosítsd a kupont.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create order
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

            // Add guest data if applicable
            if ($isGuest) {
                $orderData = array_merge($orderData, [
                    'guest_name' => $request->guest_name,
                    'guest_email' => $request->guest_email,
                    'guest_phone' => $request->guest_phone,
                    'guest_address' => $request->guest_address,
                ]);
            }

            // Add company purchase data if applicable
            if ($request->is_company_purchase) {
                $orderData = array_merge($orderData, [
                    'is_company_purchase' => true,
                    'company_name' => $request->company_name,
                    'tax_number' => $request->tax_number,
                    'billing_address' => $request->billing_address,
                ]);
            }

            $order = Order::create($orderData);

            // Create order items
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

            // Load relationships
            $order->load(['items.photo', 'user', 'coupon', 'package', 'workSession', 'paymentMethod', 'shippingMethod', 'packagePoint']);

            // Trigger OrderPlaced event for email notification
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

    /**
     * Create Stripe Checkout Session for order
     * SECURITY: Only order owner or guest with matching email can checkout
     */
    public function checkout(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        // Authorization check
        if ($order->user_id) {
            // Authenticated order - must be owner
            if (! $user || $user->id !== $order->user_id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        } else {
            // Guest order - verify email
            $guestEmail = $request->query('guest_email') ?? $request->input('guest_email');

            if (! $guestEmail || strtolower($guestEmail) !== strtolower($order->guest_email)) {
                return response()->json([
                    'message' => 'Forbidden. Guest email verification required.',
                ], 403);
            }
        }

        try {
            // Check if order is already paid
            if ($order->isPaid()) {
                return response()->json([
                    'message' => 'Order is already paid',
                ], 400);
            }

            // Load relationships needed for Stripe checkout
            $order->load(['items', 'coupon']);

            // Create Stripe Checkout Session
            $checkoutUrl = $this->stripeService->createCheckoutSession($order);

            return response()->json([
                'url' => $checkoutUrl,
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe Checkout creation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Checkout creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify payment status for an order
     * SECURITY: Only order owner or guest with matching email can verify payment
     */
    public function verifyPayment(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        // Authorization check
        if ($order->user_id) {
            // Authenticated order - must be owner or admin
            if (! $user || ($user->id !== $order->user_id && ! $user->hasRole('admin'))) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        } else {
            // Guest order - verify email
            $guestEmail = $request->query('guest_email') ?? $request->input('guest_email');

            if (! $guestEmail || strtolower($guestEmail) !== strtolower($order->guest_email)) {
                return response()->json([
                    'message' => 'Forbidden. Guest email verification required.',
                ], 403);
            }
        }

        $sessionId = $request->query('session_id');

        if (! $sessionId) {
            return response()->json([
                'message' => 'Session ID is required',
            ], 400);
        }

        try {
            // Retrieve Stripe session and verify payment
            $session = \Stripe\Checkout\Session::retrieve($sessionId);

            // Verify that this session belongs to this order
            $orderIdFromSession = $session->metadata->order_id ?? $session->client_reference_id;

            if ((int) $orderIdFromSession !== $order->id) {
                return response()->json([
                    'message' => 'Session does not match order',
                ], 400);
            }

            // Update order status if payment successful
            if ($session->payment_status === 'paid' && $order->status === 'payment_pending') {
                $order->update([
                    'status' => 'paid',
                    'stripe_pi' => $session->payment_intent,
                ]);

                Log::info('Order payment verified', [
                    'order_id' => $order->id,
                    'session_id' => $sessionId,
                ]);
            }

            return response()->json([
                'order' => $order->load(['items.photo', 'user', 'coupon', 'package', 'workSession']),
                'payment_status' => $session->payment_status,
            ]);
        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'order_id' => $order->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Payment verification failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download invoice PDF for an order
     * SECURITY: Only order owner or guest with matching email can download invoice
     */
    public function downloadInvoice(Request $request, Order $order): Response
    {
        // Check if invoice exists
        if (! $order->invoice_no) {
            abort(404, 'Számla nem található');
        }

        $user = $request->user();

        // Authorization check
        if ($order->user_id) {
            // Authenticated order - must be owner or admin
            if (! $user) {
                abort(401, 'Bejelentkezés szükséges');
            }

            if ($user->id !== $order->user_id && ! $user->hasRole('admin')) {
                abort(403, 'Nincs jogosultság');
            }
        } else {
            // Guest order - verify email
            $guestEmail = $request->query('guest_email') ?? $request->input('guest_email');

            if (! $guestEmail || strtolower($guestEmail) !== strtolower($order->guest_email)) {
                abort(403, 'Vendég email ellenőrzése szükséges');
            }
        }

        try {
            $pdfContent = $this->invoicingService->getInvoicePdf($order);

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="invoice-'.$order->invoice_no.'.pdf"');
        } catch (\Exception $e) {
            Log::error('Invoice PDF download failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Számla letöltése sikertelen');
        }
    }
}

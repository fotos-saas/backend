<?php

namespace App\Http\Controllers\Api;

use App\Actions\Order\CreateOrderAction;
use App\Actions\Order\VerifyOrderPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Order\StoreOrderRequest;
use App\Models\Order;
use App\Services\InvoicingService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
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
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrderAccess($request, $order);

        $order->load(['items.photo', 'user', 'coupon', 'package', 'workSession']);

        return response()->json($order);
    }

    /**
     * Create a new order
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        return app(CreateOrderAction::class)->execute($request);
    }

    /**
     * Create Stripe Checkout Session for order
     */
    public function checkout(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrderAccess($request, $order);

        try {
            if ($order->isPaid()) {
                return response()->json([
                    'message' => 'Order is already paid',
                ], 400);
            }

            $order->load(['items', 'coupon']);

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
     */
    public function verifyPayment(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrderAccess($request, $order);

        $sessionId = $request->query('session_id');

        if (! $sessionId) {
            return response()->json([
                'message' => 'Session ID is required',
            ], 400);
        }

        return app(VerifyOrderPaymentAction::class)->execute($order, $sessionId);
    }

    /**
     * Download invoice PDF for an order
     */
    public function downloadInvoice(Request $request, Order $order): Response
    {
        if (! $order->invoice_no) {
            abort(404, 'Számla nem található');
        }

        $this->authorizeOrderAccess($request, $order);

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

    /**
     * Authorize access to an order (owner, admin, or guest with matching email).
     */
    private function authorizeOrderAccess(Request $request, Order $order): void
    {
        $user = $request->user();

        if ($order->user_id) {
            if (! $user) {
                abort(401, 'Unauthorized');
            }
            if ($user->id !== $order->user_id && ! $user->hasRole('admin')) {
                abort(403, 'Forbidden');
            }
        } else {
            $guestEmail = $request->query('guest_email') ?? $request->input('guest_email');
            if (! $guestEmail || strtolower($guestEmail) !== strtolower($order->guest_email)) {
                abort(403, 'Forbidden. Guest email verification required.');
            }
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cart\AddCartItemRequest;
use App\Http\Requests\Api\Cart\CalculateCartPriceRequest;
use App\Http\Requests\Api\Cart\MergeGuestCartRequest;
use App\Http\Requests\Api\Cart\SyncCartRequest;
use App\Http\Requests\Api\Cart\UpdateCartItemRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Price;
use App\Models\PriceList;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartController extends Controller
{
    /**
     * Get current cart for authenticated user or guest session
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $cart = $this->getOrCreateCart($request);

        return $this->jsonWithSessionToken($request, [
            'data' => $cart->load(['items.photo', 'items.printSize', 'workSession', 'package']),
        ]);
    }

    /**
     * Add item to cart
     */
    public function addItem(AddCartItemRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        $cart = $this->getOrCreateCart($request);

        // Update work session if provided
        if ($cart->work_session_id !== $validated['workSessionId']) {
            $cart->update(['work_session_id' => $validated['workSessionId']]);
        }

        // Check if item already exists, update qty or create new
        $existingItem = $cart->items()
            ->where('photo_id', $validated['photoId'])
            ->where('print_size_id', $validated['printSizeId'])
            ->first();

        if ($existingItem) {
            $existingItem->update(['qty' => $existingItem->qty + $validated['qty']]);
            $item = $existingItem;
        } else {
            $item = $cart->items()->create([
                'photo_id' => $validated['photoId'],
                'print_size_id' => $validated['printSizeId'],
                'qty' => $validated['qty'],
                'type' => $validated['printSizeId'] ? 'print' : 'digital',
            ]);
        }

        return $this->jsonWithSessionToken($request, [
            'data' => $item->load(['photo', 'printSize']),
        ], 201);
    }

    /**
     * Update cart item quantity
     */
    public function updateItem(UpdateCartItemRequest $request, CartItem $cartItem)
    {
        $validated = $request->validated();

        $cartItem->update(['qty' => $validated['qty']]);

        return response()->json([
            'data' => $cartItem->load(['photo', 'printSize']),
        ]);
    }

    /**
     * Remove item from cart
     */
    public function removeItem(CartItem $cartItem)
    {
        $cartItem->delete();

        return response()->json(null, 204);
    }

    /**
     * Sync entire cart from localStorage
     */
    public function sync(SyncCartRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        $cart = $this->getOrCreateCart($request);

        // Update cart metadata
        $cart->update([
            'work_session_id' => $validated['workSessionId'],
            'package_id' => $validated['packageId'] ?? null,
        ]);

        // Clear existing items
        $cart->items()->delete();

        // Add new items
        foreach ($validated['items'] as $itemData) {
            $cart->items()->create([
                'photo_id' => $itemData['photoId'],
                'print_size_id' => $itemData['printSizeId'] ?? null,
                'qty' => $itemData['qty'],
                'type' => isset($itemData['printSizeId']) ? 'print' : 'digital',
            ]);
        }

        return $this->jsonWithSessionToken($request, [
            'data' => $cart->load(['items.photo', 'items.printSize']),
        ]);
    }

    /**
     * Clear all items from cart
     */
    public function clear(Request $request): \Illuminate\Http\JsonResponse
    {
        $cart = $this->getOrCreateCart($request);
        $cart->items()->delete();

        return $this->jsonWithSessionToken($request, null, 204);
    }

    /**
     * Merge guest cart with user cart after login
     */
    public function mergeGuestCart(MergeGuestCartRequest $request)
    {
        $validated = $request->validated();

        $user = $request->user();
        $guestCart = Cart::forSession($validated['sessionToken'])->first();

        if (! $guestCart || $guestCart->items->isEmpty()) {
            return response()->json([
                'message' => 'Nincs vendég kosár',
            ]);
        }

        // Get or create user cart
        $userCart = Cart::forUser($user->id)->active()->first();

        if (! $userCart) {
            // Convert guest cart to user cart
            $guestCart->update([
                'user_id' => $user->id,
                'session_token' => null,
                'expires_at' => null,
            ]);
            $userCart = $guestCart;
        } else {
            // Merge items from guest cart to user cart
            foreach ($guestCart->items as $guestItem) {
                $existingItem = $userCart->items()
                    ->where('photo_id', $guestItem->photo_id)
                    ->where('print_size_id', $guestItem->print_size_id)
                    ->first();

                if ($existingItem) {
                    $existingItem->update(['qty' => $existingItem->qty + $guestItem->qty]);
                } else {
                    $userCart->items()->create([
                        'photo_id' => $guestItem->photo_id,
                        'print_size_id' => $guestItem->print_size_id,
                        'qty' => $guestItem->qty,
                        'type' => $guestItem->type,
                    ]);
                }
            }

            // Delete guest cart
            $guestCart->delete();
        }

        return response()->json([
            'data' => $userCart->load(['items.photo', 'items.printSize']),
        ]);
    }

    /**
     * Calculate cart price (legacy endpoint)
     */
    public function calculatePrice(CalculateCartPriceRequest $request)
    {
        $validated = $request->validated();

        $priceList = PriceList::latest()->first();

        if (! $priceList) {
            return response()->json(['error' => 'Nincs elérhető árlista'], 400);
        }

        $subtotal = 0;

        $sizeIds = collect($validated['items'])
            ->where('type', 'print')
            ->pluck('sizeId')
            ->filter()
            ->unique();

        $prices = Price::where('price_list_id', $priceList->id)
            ->whereIn('print_size_id', $sizeIds)
            ->pluck('price', 'print_size_id');

        foreach ($validated['items'] as $item) {
            if ($item['type'] === 'print' && isset($item['sizeId'])) {
                $subtotal += ($prices[$item['sizeId']] ?? 0) * $item['qty'];
            }
        }

        $shipping = $subtotal > 0 ? config('shop.default_shipping_cost', 1500) : 0;
        $total = $subtotal + $shipping;

        return response()->json([
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total' => $total,
            'currency' => 'HUF',
        ]);
    }

    /**
     * Create JSON response with optional session token header.
     */
    private function jsonWithSessionToken(Request $request, mixed $data, int $status = 200): \Illuminate\Http\JsonResponse
    {
        $response = response()->json($data, $status);

        $sessionToken = $request->attributes->get('session_token');
        if ($sessionToken) {
            $response->header('X-Session-Token', $sessionToken);
        }

        return $response;
    }

    /**
     * Get or create cart for current user/session
     */
    private function getOrCreateCart(Request $request): Cart
    {
        $user = $request->user();

        if ($user) {
            // Authenticated user
            $cart = Cart::forUser($user->id)->active()->first();

            if (! $cart) {
                $cart = Cart::create([
                    'user_id' => $user->id,
                    'status' => 'draft',
                ]);
            }
        } else {
            // Guest user
            $sessionToken = $request->header('X-Session-Token');

            if (! $sessionToken) {
                $sessionToken = Str::random(64);
            }

            $cart = Cart::forSession($sessionToken)->active()->first();

            if (! $cart) {
                $cart = Cart::create([
                    'session_token' => $sessionToken,
                    'status' => 'draft',
                    'expires_at' => now()->addDays(30),
                ]);
            }

            // Store token on request so callers can add it to the response
            $request->attributes->set('session_token', $sessionToken);
        }

        return $cart;
    }
}

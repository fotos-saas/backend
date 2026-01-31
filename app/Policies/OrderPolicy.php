<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can view any orders.
     * Only authenticated users can list their own orders.
     */
    public function viewAny(?User $user): bool
    {
        // Listing orders requires authentication
        return $user !== null;
    }

    /**
     * Determine whether the user can view the order.
     * Users can view their own orders, or guest orders if email matches.
     */
    public function view(?User $user, Order $order): bool
    {
        // Authenticated user - check user_id match
        if ($user && $order->user_id === $user->id) {
            return true;
        }

        // Guest order - check email match (request parameter needed)
        // This will be handled in the controller via request()->input('email')
        if ($order->isGuest()) {
            // Email verification happens in controller
            return true;
        }

        // Admin users can view all orders
        if ($user && $user->hasRole('admin')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create orders.
     * Both authenticated and guest users can create orders.
     */
    public function create(?User $user): bool
    {
        // Anyone can create orders (guest or authenticated)
        return true;
    }

    /**
     * Determine whether the user can update the order.
     * Only admins can update orders.
     */
    public function update(?User $user, Order $order): bool
    {
        return $user && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the order.
     * Only admins can delete orders.
     */
    public function delete(?User $user, Order $order): bool
    {
        return $user && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can checkout the order.
     * Users can checkout their own orders, or guest orders if email matches.
     */
    public function checkout(?User $user, Order $order): bool
    {
        // Same logic as view
        if ($user && $order->user_id === $user->id) {
            return true;
        }

        if ($order->isGuest()) {
            return true; // Email verification in controller
        }

        return false;
    }

    /**
     * Determine whether the user can verify payment for the order.
     */
    public function verifyPayment(?User $user, Order $order): bool
    {
        // Same logic as view
        return $this->view($user, $order);
    }

    /**
     * Determine whether the user can download invoice.
     */
    public function downloadInvoice(?User $user, Order $order): bool
    {
        // Same logic as view
        return $this->view($user, $order);
    }
}

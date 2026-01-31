<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Order $order, public string $previousStatus, public string $newStatus) {}
}

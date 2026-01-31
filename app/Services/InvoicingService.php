<?php

namespace App\Services;

use App\Enums\InvoicingProviderType;
use App\Models\InvoicingProvider;
use App\Models\Order;
use App\Services\Invoicing\BillingoService;
use App\Services\Invoicing\InvoicingServiceInterface;
use App\Services\Invoicing\SzamlazzHuService;
use Illuminate\Support\Facades\Log;

class InvoicingService
{
    /**
     * Get the active invoicing provider service
     */
    public function getActiveProvider(): InvoicingServiceInterface
    {
        $provider = InvoicingProvider::query()
            ->active()
            ->first();

        if (! $provider) {
            throw new \Exception('No active invoicing provider configured');
        }

        return match ($provider->provider_type) {
            InvoicingProviderType::SzamlazzHu => new SzamlazzHuService($provider),
            InvoicingProviderType::Billingo => new BillingoService($provider),
        };
    }

    /**
     * Issue invoice for an order
     */
    public function issueInvoiceForOrder(Order $order): array
    {
        // Check if order is paid
        if (! $order->isPaid()) {
            throw new \Exception('Cannot issue invoice for unpaid order');
        }

        // Check if invoice already exists
        if ($order->invoice_no) {
            throw new \Exception('Invoice already issued for this order');
        }

        $provider = $this->getActiveProvider();

        try {
            $result = $provider->issueInvoice($order);

            // Update order with invoice information
            $order->update([
                'invoice_no' => $result['invoice_number'],
                'invoice_issued_at' => now(),
            ]);

            Log::info('Invoice issued successfully', [
                'order_id' => $order->id,
                'invoice_no' => $result['invoice_number'],
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Invoice issuance failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get invoice PDF for an order
     */
    public function getInvoicePdf(Order $order): string
    {
        if (! $order->invoice_no) {
            throw new \Exception('No invoice found for this order');
        }

        $provider = $this->getActiveProvider();

        try {
            return $provider->getInvoicePdf($order->invoice_no);
        } catch (\Exception $e) {
            Log::error('Invoice PDF retrieval failed', [
                'order_id' => $order->id,
                'invoice_no' => $order->invoice_no,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Cancel invoice for an order
     */
    public function cancelInvoice(Order $order): bool
    {
        if (! $order->invoice_no) {
            throw new \Exception('No invoice found for this order');
        }

        $provider = $this->getActiveProvider();

        try {
            $result = $provider->cancelInvoice($order->invoice_no);

            if ($result) {
                $order->update([
                    'invoice_no' => null,
                    'invoice_issued_at' => null,
                ]);

                Log::info('Invoice cancelled successfully', [
                    'order_id' => $order->id,
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Invoice cancellation failed', [
                'order_id' => $order->id,
                'invoice_no' => $order->invoice_no,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

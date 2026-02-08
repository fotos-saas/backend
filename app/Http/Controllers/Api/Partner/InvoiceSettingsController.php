<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\UpdateInvoiceSettingsRequest;
use App\Models\TabloPartner;
use App\Services\Invoice\InvoiceService;
use Illuminate\Http\JsonResponse;

class InvoiceSettingsController extends Controller
{
    use PartnerAuthTrait;

    public function show(): JsonResponse
    {
        $partner = TabloPartner::findOrFail($this->getPartnerIdOrFail());

        return response()->json([
            'data' => [
                'invoice_provider' => $partner->invoice_provider?->value ?? 'szamlazz_hu',
                'invoice_enabled' => $partner->invoice_enabled,
                'has_api_key' => $partner->invoice_api_key !== null,
                'szamlazz_bank_name' => $partner->szamlazz_bank_name,
                'szamlazz_bank_account' => $partner->szamlazz_bank_account,
                'szamlazz_reply_email' => $partner->szamlazz_reply_email,
                'billingo_block_id' => $partner->billingo_block_id,
                'billingo_bank_account_id' => $partner->billingo_bank_account_id,
                'invoice_prefix' => $partner->invoice_prefix ?? 'PS',
                'invoice_currency' => $partner->invoice_currency ?? 'HUF',
                'invoice_language' => $partner->invoice_language ?? 'hu',
                'invoice_due_days' => $partner->invoice_due_days ?? 8,
                'invoice_vat_percentage' => $partner->invoice_vat_percentage ?? 27.00,
                'invoice_comment' => $partner->invoice_comment,
                'invoice_eu_vat' => $partner->invoice_eu_vat ?? false,
            ],
        ]);
    }

    public function update(UpdateInvoiceSettingsRequest $request): JsonResponse
    {
        $partner = TabloPartner::findOrFail($this->getPartnerIdOrFail());
        $validated = $request->validated();

        // API kulcs encrypted mentése (csak ha változott)
        if (isset($validated['invoice_api_key']) && $validated['invoice_api_key'] !== null) {
            $partner->setEncryptedApiKey($validated['invoice_api_key']);
            unset($validated['invoice_api_key']);
        }

        $partner->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Számlázási beállítások sikeresen mentve',
        ]);
    }

    public function validateApiKey(InvoiceService $invoiceService): JsonResponse
    {
        $partner = TabloPartner::findOrFail($this->getPartnerIdOrFail());

        if (! $partner->invoice_api_key) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs API kulcs megadva',
            ], 422);
        }

        $valid = $invoiceService->validateCredentials($partner);

        return response()->json([
            'success' => $valid,
            'message' => $valid
                ? 'Az API kulcs érvényes'
                : 'Az API kulcs érvénytelen vagy a szolgáltató nem elérhető',
        ]);
    }
}

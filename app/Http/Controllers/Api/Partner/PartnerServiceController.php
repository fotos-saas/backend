<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Requests\Api\Partner\StorePartnerServiceRequest;
use App\Http\Requests\Api\Partner\UpdatePartnerServiceRequest;
use App\Models\PartnerService;
use Illuminate\Http\JsonResponse;

class PartnerServiceController extends Controller
{
    use PartnerAuthTrait;

    public function index(): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $services = PartnerService::forPartner($partnerId)
            ->ordered()
            ->get();

        return $this->successResponse([
            'services' => $services->map(fn (PartnerService $s) => $this->formatService($s)),
        ]);
    }

    public function store(StorePartnerServiceRequest $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $service = PartnerService::create([
            'partner_id' => $partnerId,
            ...$request->validated(),
        ]);

        return $this->successResponse([
            'service' => $this->formatService($service),
        ], 201);
    }

    public function update(UpdatePartnerServiceRequest $request, int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $service = PartnerService::forPartner($partnerId)->findOrFail($id);
        $service->update($request->validated());

        return $this->successResponse([
            'service' => $this->formatService($service->fresh()),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $service = PartnerService::forPartner($partnerId)->findOrFail($id);

        if ($service->charges()->exists()) {
            $service->update(['is_active' => false]);
            return $this->successResponse(['message' => 'Szolgáltatás inaktiválva (vannak hozzá tartozó terhelések).']);
        }

        $service->delete();

        return $this->successResponse(['message' => 'Szolgáltatás törölve.']);
    }

    /**
     * Alapértelmezett szolgáltatások betöltése.
     */
    public function seedDefaults(): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $existing = PartnerService::forPartner($partnerId)->count();
        if ($existing > 0) {
            return $this->errorResponse('Már vannak szolgáltatások. Csak üres katalógus esetén használható.', 422);
        }

        foreach (PartnerService::DEFAULT_SERVICES as $default) {
            PartnerService::create([
                'partner_id' => $partnerId,
                ...$default,
            ]);
        }

        $services = PartnerService::forPartner($partnerId)->ordered()->get();

        return $this->successResponse([
            'services' => $services->map(fn (PartnerService $s) => $this->formatService($s)),
            'message' => 'Alapértelmezett szolgáltatások betöltve.',
        ]);
    }

    private function formatService(PartnerService $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'service_type' => $service->service_type,
            'default_price' => $service->default_price,
            'currency' => $service->currency,
            'vat_percentage' => (float) $service->vat_percentage,
            'is_active' => $service->is_active,
            'sort_order' => $service->sort_order,
        ];
    }
}

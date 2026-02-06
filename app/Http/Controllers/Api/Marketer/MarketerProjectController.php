<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Helpers\QueryHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Requests\Api\Marketer\StoreProjectRequest;
use App\Models\TabloProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketerProjectController extends Controller
{
    use PartnerAuthTrait;

    /**
     * List projects with pagination and search.
     */
    public function projects(Request $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $perPage = min((int) $request->input('per_page', 15), 50);
        $search = $request->input('search');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $status = $request->input('status');

        $allowedSortFields = ['created_at', 'photo_date', 'class_year'];
        if (! in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }

        $query = TabloProject::with(['school', 'contacts', 'tabloStatus', 'qrCodes' => function ($q) {
            $q->active();
        }])
            ->where('partner_id', $partnerId);

        if ($search) {
            $pattern = QueryHelper::safeLikePattern($search);
            $query->where(function ($q) use ($pattern) {
                $q->where('class_name', 'ILIKE', $pattern)
                    ->orWhere('name', 'ILIKE', $pattern)
                    ->orWhereHas('school', function ($sq) use ($pattern) {
                        $sq->where('name', 'ILIKE', $pattern)
                            ->orWhere('city', 'ILIKE', $pattern);
                    });
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $projects = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        $projects->getCollection()->transform(function ($project) {
            $primaryContact = $project->contacts->firstWhere('is_primary', true)
                ?? $project->contacts->first();

            $activeQrCode = $project->qrCodes->first();

            return [
                'id' => $project->id,
                'name' => $project->display_name,
                'schoolName' => $project->school?->name,
                'schoolCity' => $project->school?->city,
                'className' => $project->class_name,
                'classYear' => $project->class_year,
                'status' => $project->status?->value,
                'statusLabel' => $project->status?->label() ?? 'Ismeretlen',
                'tabloStatus' => $project->tabloStatus?->toApiResponse(),
                'photoDate' => $project->photo_date?->format('Y-m-d'),
                'deadline' => $project->deadline?->format('Y-m-d'),
                'contact' => $primaryContact ? [
                    'name' => $primaryContact->name,
                    'email' => $primaryContact->email,
                    'phone' => $primaryContact->phone,
                ] : null,
                'hasActiveQrCode' => $activeQrCode !== null,
                'qrCodeId' => $activeQrCode?->id,
                'createdAt' => $project->created_at->toIso8601String(),
            ];
        });

        return response()->json($projects);
    }

    /**
     * Get project details.
     */
    public function projectDetails(int $projectId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $project = TabloProject::with([
            'school',
            'partner',
            'contacts',
            'tabloStatus',
            'qrCodes' => function ($q) {
                $q->orderBy('created_at', 'desc');
            },
        ])
            ->where('partner_id', $partnerId)
            ->findOrFail($projectId);

        $primaryContact = $project->contacts->firstWhere('is_primary', true)
            ?? $project->contacts->first();

        $activeQrCodes = $project->qrCodes->where('is_active', true);
        $activeQrCode = $activeQrCodes->first();

        return response()->json([
            'id' => $project->id,
            'name' => $project->display_name,
            'school' => $project->school ? [
                'id' => $project->school->id,
                'name' => $project->school->name,
                'city' => $project->school->city,
            ] : null,
            'partner' => $project->partner ? [
                'id' => $project->partner->id,
                'name' => $project->partner->name,
            ] : null,
            'className' => $project->class_name,
            'classYear' => $project->class_year,
            'status' => $project->status?->value,
            'statusLabel' => $project->status?->label() ?? 'Ismeretlen',
            'tabloStatus' => $project->tabloStatus?->toApiResponse(),
            'photoDate' => $project->photo_date?->format('Y-m-d'),
            'deadline' => $project->deadline?->format('Y-m-d'),
            'expectedClassSize' => $project->expected_class_size,
            'contact' => $primaryContact ? [
                'id' => $primaryContact->id,
                'name' => $primaryContact->name,
                'email' => $primaryContact->email,
                'phone' => $primaryContact->phone,
            ] : null,
            'contacts' => $project->contacts->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->phone,
                'isPrimary' => $c->is_primary,
            ]),
            'qrCode' => $activeQrCode ? [
                'id' => $activeQrCode->id,
                'code' => $activeQrCode->code,
                'type' => $activeQrCode->type?->value ?? 'coordinator',
                'typeLabel' => $activeQrCode->type?->label() ?? 'Kapcsolattartó',
                'usageCount' => $activeQrCode->usage_count,
                'maxUsages' => $activeQrCode->max_usages,
                'expiresAt' => $activeQrCode->expires_at?->toIso8601String(),
                'isValid' => $activeQrCode->isValid(),
                'registrationUrl' => $activeQrCode->getRegistrationUrl(),
            ] : null,
            'activeQrCodes' => $activeQrCodes->values()->map(fn ($qr) => [
                'id' => $qr->id,
                'code' => $qr->code,
                'type' => $qr->type?->value ?? 'coordinator',
                'typeLabel' => $qr->type?->label() ?? 'Kapcsolattartó',
                'usageCount' => $qr->usage_count,
                'isValid' => $qr->isValid(),
                'registrationUrl' => $qr->getRegistrationUrl(),
            ]),
            'qrCodesHistory' => $project->qrCodes->map(fn ($qr) => [
                'id' => $qr->id,
                'code' => $qr->code,
                'type' => $qr->type?->value ?? 'coordinator',
                'typeLabel' => $qr->type?->label() ?? 'Kapcsolattartó',
                'isActive' => $qr->is_active,
                'usageCount' => $qr->usage_count,
                'createdAt' => $qr->created_at->toIso8601String(),
            ]),
            'createdAt' => $project->created_at->toIso8601String(),
            'updatedAt' => $project->updated_at->toIso8601String(),
        ]);
    }

    /**
     * Create a new project for the user's partner.
     */
    public function storeProject(StoreProjectRequest $request): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();

        $project = TabloProject::create([
            'partner_id' => $partnerId,
            'school_id' => $request->input('school_id'),
            'class_name' => $request->input('class_name'),
            'class_year' => $request->input('class_year'),
            'status' => \App\Enums\TabloProjectStatus::NotStarted,
        ]);

        $project->load(['school']);

        return response()->json([
            'success' => true,
            'message' => 'Projekt sikeresen létrehozva',
            'data' => [
                'id' => $project->id,
                'name' => $project->display_name,
                'schoolName' => $project->school?->name,
                'schoolCity' => $project->school?->city,
                'className' => $project->class_name,
                'classYear' => $project->class_year,
                'status' => $project->status?->value,
                'statusLabel' => $project->status?->label() ?? 'Ismeretlen',
                'tabloStatus' => null,
                'photoDate' => null,
                'deadline' => null,
                'contact' => null,
                'hasActiveQrCode' => false,
                'qrCodeId' => null,
                'createdAt' => $project->created_at->toIso8601String(),
            ],
        ], 201);
    }
}

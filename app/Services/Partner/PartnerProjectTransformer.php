<?php

namespace App\Services\Partner;

use App\Models\TabloProject;

/**
 * Partner projekt adatok transzformálása API response formátumba.
 *
 * Lista nézet, részletes nézet és QR kód formázás.
 */
class PartnerProjectTransformer
{
    /**
     * Projekt transzformálása lista nézethez.
     */
    public function toListItem(TabloProject $project): array
    {
        $primaryContact = $project->contacts->first(fn ($c) => $c->pivot->is_primary ?? false)
            ?? $project->contacts->first();

        $activeQrCode = $project->qrCodes->first();
        $samples = $project->media->where('collection_name', 'samples');
        $firstSample = $samples->first();
        $draftPhotoCount = $project->media->where('collection_name', 'tablo_pending')->count();

        return [
            'id' => $project->id,
            'name' => $project->display_name,
            'schoolName' => $project->school?->name,
            'schoolCity' => $project->school?->city,
            'className' => $project->class_name,
            'classYear' => $project->class_year,
            'status' => $project->status?->value,
            'statusLabel' => $project->status?->label() ?? 'Ismeretlen',
            'statusColor' => $project->status?->tailwindColor() ?? 'gray',
            'tabloStatus' => $project->tabloStatus?->toApiResponse(),
            'photoDate' => $project->photo_date?->format('Y-m-d'),
            'deadline' => $project->deadline?->format('Y-m-d'),
            'guestsCount' => $project->guests_count ?? 0,
            'expectedClassSize' => $project->expected_class_size,
            'missingCount' => $project->missing_count ?? 0,
            'missingStudentsCount' => $project->missing_students_count ?? 0,
            'missingTeachersCount' => $project->missing_teachers_count ?? 0,
            'samplesCount' => $samples->count(),
            'sampleThumbUrl' => $firstSample?->getUrl('thumb'),
            'draftPhotoCount' => $draftPhotoCount,
            'contact' => $primaryContact ? [
                'name' => $primaryContact->name,
                'email' => $primaryContact->email,
                'phone' => $primaryContact->phone,
            ] : null,
            'hasActiveQrCode' => $activeQrCode !== null,
            'isAware' => $project->is_aware,
            'createdAt' => $project->created_at->toIso8601String(),
            'finalizedAt' => $project->data['finalized_at'] ?? null,
        ];
    }

    /**
     * Projekt transzformálása részletes nézethez.
     */
    public function toDetailResponse(TabloProject $project): array
    {
        $primaryContact = $project->contacts->first(fn ($c) => $c->pivot->is_primary)
            ?? $project->contacts->first();

        $activeQrCodes = $project->qrCodes->where('is_active', true);
        $activeQrCode = $activeQrCodes->first();
        $samplesCount = $project->getMedia('samples')->count();

        return [
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
            'statusColor' => $project->status?->tailwindColor() ?? 'gray',
            'tabloStatus' => $project->tabloStatus?->toApiResponse(),
            'photoDate' => $project->photo_date?->format('Y-m-d'),
            'deadline' => $project->deadline?->format('Y-m-d'),
            'expectedClassSize' => $project->expected_class_size,
            'finalizedAt' => $project->data['finalized_at'] ?? null,
            'draftPhotoCount' => $project->getMedia('tablo_pending')->count(),
            'guestsCount' => $project->guests_count ?? 0,
            'missingCount' => $project->missing_count ?? 0,
            'samplesCount' => $samplesCount,
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
                'isPrimary' => $c->pivot->is_primary ?? false,
            ]),
            'qrCode' => $activeQrCode ? $this->qrCodeFull($activeQrCode) : null,
            'activeQrCodes' => $activeQrCodes->values()->map(
                fn ($qr) => $this->qrCodeSummary($qr)
            ),
            'qrCodesHistory' => $project->qrCodes->map(fn ($qr) => [
                'id' => $qr->id,
                'code' => $qr->code,
                'type' => $qr->type?->value ?? 'coordinator',
                'typeLabel' => $qr->type?->label() ?? 'Kapcsolattartó',
                'isActive' => $qr->is_active,
                'usageCount' => $qr->usage_count,
                'createdAt' => $qr->created_at->toIso8601String(),
            ]),
            'tabloGalleryId' => $project->tablo_gallery_id,
            'galleryPhotosCount' => $project->gallery?->getMedia('photos')->count() ?? 0,
            ...$this->personsData($project),
            'createdAt' => $project->created_at->toIso8601String(),
            'updatedAt' => $project->updated_at->toIso8601String(),
        ];
    }

    /**
     * Személyek statisztika és preview a részletes nézethez.
     * Fotó resolution: override → archive.active_photo → legacy media_id
     */
    private function personsData(TabloProject $project): array
    {
        $persons = $project->relationLoaded('persons') ? $project->persons : collect();

        $students = $persons->where('type', 'student');
        $teachers = $persons->where('type', 'teacher');

        $studentsWithPhoto = $students->filter(fn ($s) => $s->hasEffectivePhoto())->count();
        $teachersWithPhoto = $teachers->filter(fn ($t) => $t->hasEffectivePhoto())->count();

        $preview = $students->take(8)->merge($teachers->take(8))->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'type' => $p->type,
            'hasPhoto' => $p->hasEffectivePhoto(),
            'photoThumbUrl' => $p->getEffectivePhotoThumbUrl(),
        ])->values();

        return [
            'personsCount' => $persons->count(),
            'studentsCount' => $students->count(),
            'teachersCount' => $teachers->count(),
            'studentsWithPhotoCount' => $studentsWithPhoto,
            'teachersWithPhotoCount' => $teachersWithPhoto,
            'personsPreview' => $preview,
        ];
    }

    /**
     * QR kód teljes transzformáció (részletes nézet).
     */
    public function qrCodeFull($qrCode): array
    {
        return [
            'id' => $qrCode->id,
            'code' => $qrCode->code,
            'type' => $qrCode->type?->value ?? 'coordinator',
            'typeLabel' => $qrCode->type?->label() ?? 'Kapcsolattartó',
            'usageCount' => $qrCode->usage_count,
            'maxUsages' => $qrCode->max_usages,
            'expiresAt' => $qrCode->expires_at?->toIso8601String(),
            'isValid' => $qrCode->isValid(),
            'registrationUrl' => $qrCode->getRegistrationUrl(),
        ];
    }

    /**
     * QR kód rövid transzformáció (lista nézet).
     */
    public function qrCodeSummary($qrCode): array
    {
        return [
            'id' => $qrCode->id,
            'code' => $qrCode->code,
            'type' => $qrCode->type?->value ?? 'coordinator',
            'typeLabel' => $qrCode->type?->label() ?? 'Kapcsolattartó',
            'usageCount' => $qrCode->usage_count,
            'isValid' => $qrCode->isValid(),
            'registrationUrl' => $qrCode->getRegistrationUrl(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Tablo;

use App\Models\TabloProject;
use App\Models\TabloSampleTemplate;
use App\Models\TabloUserProgress;
use App\Models\User;

/**
 * Validate tablo session és összegyűjti a projekt adatokat.
 *
 * A validateTabloSession végpont üzleti logikája.
 */
class ValidateTabloSessionAction
{
    public function execute(User $user, object $token): ?array
    {
        $tabloProject = TabloProject::with([
            'school', 'partner.users', 'contacts', 'persons', 'tabloStatus', 'gallery',
        ])->find($token->tablo_project_id);

        if (! $tabloProject) {
            return null;
        }

        $coordinators = $this->getCoordinators($tabloProject);
        $primaryContact = $this->getPrimaryContact($tabloProject);
        $missingPersons = $this->getMissingPersons($tabloProject);
        $missingWithoutPhoto = $missingPersons->where('hasPhoto', false);
        $tokenInfo = $this->resolveTokenType($token);
        $progress = $this->getUserProgress($user, $tabloProject);

        return [
            'valid' => true,
            'project' => [
                'id' => $tabloProject->id,
                'name' => $tabloProject->display_name,
                'schoolName' => $tabloProject->school?->name,
                'className' => $tabloProject->class_name,
                'classYear' => $tabloProject->class_year,
                'partnerName' => $tabloProject->partner?->name,
                'partnerEmail' => $tabloProject->partner?->email,
                'partnerPhone' => $tabloProject->partner?->phone,
                'coordinators' => $coordinators,
                'contacts' => $primaryContact ? [[
                    'id' => $primaryContact->id,
                    'name' => $primaryContact->name,
                    'email' => $primaryContact->email,
                    'phone' => $primaryContact->phone,
                ]] : [],
                'hasOrderData' => $tabloProject->hasOrderData(),
                'hasOrderAnalysis' => $tabloProject->hasOrderAnalysis(),
                'lastActivityAt' => $tabloProject->lastEmailDate?->toIso8601String(),
                'photoDate' => $tabloProject->photo_date?->format('Y-m-d'),
                'deadline' => $tabloProject->deadline?->format('Y-m-d'),
                'missingPersons' => $missingPersons,
                'missingStats' => [
                    'total' => $missingPersons->count(),
                    'withoutPhoto' => $missingWithoutPhoto->count(),
                    'studentsWithoutPhoto' => $missingWithoutPhoto->where('type', 'student')->count(),
                    'teachersWithoutPhoto' => $missingWithoutPhoto->where('type', 'teacher')->count(),
                ],
                'hasMissingPersons' => $missingPersons->count() > 0,
                'hasTemplateChooser' => TabloSampleTemplate::active()->exists(),
                'samplesCount' => $tabloProject->getMedia('samples')->count(),
                'activePollsCount' => $tabloProject->polls()->active()->count(),
                'expectedClassSize' => $tabloProject->expected_class_size,
                'tabloStatus' => $tabloProject->tabloStatus?->toApiResponse(),
                'userStatus' => $tabloProject->tabloStatus?->name ?? $tabloProject->user_status,
                'userStatusColor' => $tabloProject->tabloStatus?->color ?? $tabloProject->user_status_color,
                'shareUrl' => $tabloProject->hasValidShareToken() ? $tabloProject->getShareUrl() : null,
                'shareEnabled' => $tabloProject->share_token_enabled,
                'isFinalized' => ! empty($tabloProject->data['finalized_at'] ?? null),
                'workSessionId' => $tabloProject->work_session_id,
                'hasPhotoSelection' => $tabloProject->work_session_id !== null || $tabloProject->tablo_gallery_id !== null,
                'billingEnabled' => $tabloProject->partner?->billing_enabled ?? false,
                'tabloGalleryId' => $tabloProject->tablo_gallery_id,
                'hasGallery' => $tabloProject->gallery !== null,
                'photoSelectionCurrentStep' => $progress?->current_step ?? ($tabloProject->tablo_gallery_id ? 'claiming' : null),
                'photoSelectionFinalized' => $progress?->isFinalized() ?? false,
                'photoSelectionProgress' => $progress ? [
                    'claimedCount' => count(($progress->steps_data ?? [])['claimed_media_ids'] ?? []),
                    'retouchCount' => count(($progress->steps_data ?? [])['retouch_media_ids'] ?? []),
                    'hasTabloPhoto' => isset(($progress->steps_data ?? [])['tablo_media_id']),
                ] : null,
                'branding' => $tabloProject->partner?->getActiveBranding(),
                'webshop' => $this->getWebshopData($tabloProject),
            ],
            'tokenType' => $tokenInfo['type'],
            'isGuest' => $tokenInfo['isGuest'],
            'canFinalize' => $tokenInfo['canFinalize'],
            'user' => [
                'passwordSet' => (bool) $user->password_set,
            ],
        ];
    }

    private function getCoordinators(TabloProject $project): \Illuminate\Support\Collection
    {
        return $project->partner?->users
            ->filter(fn ($user) => $user->hasRole('tablo'))
            ->map(fn ($user) => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ])
            ->values() ?? collect();
    }

    private function getPrimaryContact(TabloProject $project): ?\App\Models\TabloContact
    {
        return $project->contacts
            ->filter(fn ($c) => $c->is_primary)
            ->first()
            ?? $project->contacts->sortByDesc('created_at')->first();
    }

    private function getMissingPersons(TabloProject $project): \Illuminate\Support\Collection
    {
        return $project->persons
            ->sortBy('position')
            ->map(fn ($person) => [
                'id' => $person->id,
                'name' => $person->name,
                'type' => $person->type,
                'localId' => $person->local_id,
                'hasPhoto' => $person->hasPhoto(),
            ])
            ->values();
    }

    private function resolveTokenType(object $token): array
    {
        $tokenType = match ($token->name) {
            'tablo-auth-token', 'qr-registration', 'dev-tablo-token' => 'code',
            'tablo-share-token' => 'share',
            'tablo-preview-token' => 'preview',
            default => 'unknown',
        };

        return [
            'type' => $tokenType,
            'isGuest' => in_array($tokenType, ['share', 'preview']),
            'canFinalize' => $tokenType === 'code',
        ];
    }

    private function getUserProgress(User $user, TabloProject $project): ?TabloUserProgress
    {
        if (! $project->tablo_gallery_id) {
            return null;
        }

        return TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $project->tablo_gallery_id)
            ->first();
    }

    private function getWebshopData(TabloProject $project): ?array
    {
        $partnerId = $project->tablo_partner_id;
        $settings = \App\Models\ShopSetting::where('tablo_partner_id', $partnerId)->first();

        if (! $settings || ! $settings->is_enabled) {
            return null;
        }

        $token = $project->gallery?->webshop_share_token;
        if (! $token) {
            return null;
        }

        return [
            'enabled' => true,
            'shop_url' => '/shop/'.$token,
        ];
    }
}

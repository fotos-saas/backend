<?php

namespace App\Actions\Partner;

use App\Models\TabloContact;
use App\Services\Search\SearchService;

class ListContactsAction
{
    public function __construct(
        private SearchService $searchService
    ) {}

    /**
     * Partner kontaktok listázása keresés + lapozás + limitek.
     */
    public function execute(int $partnerId, ?string $search, int $perPage): array
    {
        $query = TabloContact::where('partner_id', $partnerId)
            ->with(['projects.school']);

        if ($search) {
            $query = $this->searchService->apply($query, $search, [
                'columns' => ['name', 'email', 'phone'],
            ]);
        }

        $contacts = $query->orderBy('name')->paginate($perPage);

        $contacts->getCollection()->transform(function ($contact) {
            return $this->formatContact($contact);
        });

        $partner = auth()->user()->getEffectivePartner();
        $maxContacts = $partner?->getMaxContacts();
        $currentCount = TabloContact::where('partner_id', $partnerId)->count();

        $response = $contacts->toArray();
        $response['limits'] = [
            'current' => $currentCount,
            'max' => $maxContacts,
            'can_create' => $maxContacts === null || $currentCount < $maxContacts,
            'plan_id' => $partner?->plan ?? 'alap',
        ];

        return $response;
    }

    public function formatContact(TabloContact $contact): array
    {
        $projects = $contact->projects;
        $projectIds = $projects->pluck('id')->toArray();
        $projectNames = $projects->map(fn ($p) => $p->display_name)->toArray();
        $schoolNames = $projects->map(fn ($p) => $p->school?->name)->filter()->unique()->values()->toArray();
        $isPrimary = $projects->contains(fn ($p) => $p->pivot->is_primary);

        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'note' => $contact->note,
            'isPrimary' => $isPrimary,
            'projectIds' => $projectIds,
            'projectNames' => $projectNames,
            'schoolNames' => $schoolNames,
            'projectId' => $projectIds[0] ?? null,
            'projectName' => $projectNames[0] ?? null,
            'schoolName' => $schoolNames[0] ?? null,
            'callCount' => $contact->call_count ?? 0,
            'smsCount' => $contact->sms_count ?? 0,
        ];
    }
}

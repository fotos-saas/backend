<?php

namespace App\Actions\Tablo;

use App\Helpers\QueryHelper;
use App\Models\TabloGuestSession;
use App\Models\TabloProject;

/**
 * Résztvevők keresése @mention-höz.
 *
 * Vendégek (guests) és kapcsolattartók (contacts) keresése
 * a projekt kontextusában.
 */
class SearchParticipantsAction
{
    /**
     * @return array<int, array{id: string, type: string, name: string, display: string}>
     */
    public function execute(TabloProject $project, string $query, int $limit): array
    {
        $results = [];

        // Vendégek keresése (nem bannoltak)
        if (strlen($query) >= 1) {
            $guests = TabloGuestSession::where('tablo_project_id', $project->id)
                ->where('is_banned', false)
                ->where('guest_name', 'ilike', QueryHelper::safeLikePattern($query))
                ->orderBy('guest_name')
                ->limit($limit)
                ->get();

            foreach ($guests as $guest) {
                $results[] = [
                    'id' => 'guest_' . $guest->id,
                    'type' => 'guest',
                    'name' => $guest->guest_name,
                    'display' => $guest->guest_name,
                ];
            }
        }

        // Kapcsolattartó hozzáadása ha fér
        $contact = $project->contact;
        if ($contact && count($results) < $limit) {
            $contactName = $contact->name ?? 'Kapcsolattartó';
            if (empty($query) || stripos($contactName, $query) !== false) {
                $results[] = [
                    'id' => 'contact_' . $contact->id,
                    'type' => 'contact',
                    'name' => $contactName,
                    'display' => $contactName . ' (Kapcsolattartó)',
                ];
            }
        }

        return $results;
    }
}

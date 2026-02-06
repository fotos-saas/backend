<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Requests\Api\Marketer\AddContactRequest;
use App\Http\Requests\Api\Marketer\UpdateContactRequest;
use App\Models\TabloContact;
use Illuminate\Http\JsonResponse;

class MarketerProjectContactController extends Controller
{
    use PartnerAuthTrait;

    /**
     * Add contact to project.
     */
    public function addContact(AddContactRequest $request, int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);
        $isPrimary = $request->boolean('isPrimary', false);

        if ($isPrimary) {
            // Összes meglévő contact pivot is_primary-ját false-ra
            foreach ($project->contacts as $existing) {
                $project->contacts()->updateExistingPivot($existing->id, ['is_primary' => false]);
            }
        }

        // Contact létrehozása
        $contact = TabloContact::create([
            'partner_id' => $project->partner_id,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
        ]);

        // Pivot kapcsolat hozzáadása
        $project->contacts()->attach($contact->id, ['is_primary' => $isPrimary]);

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen hozzáadva',
            'data' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'isPrimary' => $isPrimary,
            ],
        ], 201);
    }

    /**
     * Update contact.
     */
    public function updateContact(UpdateContactRequest $request, int $projectId, int $contactId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);
        $contact = $project->contacts()->findOrFail($contactId);
        $isPrimary = $request->has('isPrimary') ? $request->boolean('isPrimary') : (bool) $contact->pivot->is_primary;

        if ($request->boolean('isPrimary')) {
            // Összes többi contact pivot is_primary-ját false-ra
            foreach ($project->contacts()->where('tablo_contacts.id', '!=', $contactId)->get() as $other) {
                $project->contacts()->updateExistingPivot($other->id, ['is_primary' => false]);
            }
        }

        // Contact adatok frissítése
        $contact->update([
            'name' => $request->input('name', $contact->name),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
        ]);

        // Pivot is_primary frissítése
        $project->contacts()->updateExistingPivot($contactId, ['is_primary' => $isPrimary]);

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen frissítve',
            'data' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'isPrimary' => $isPrimary,
            ],
        ]);
    }

    /**
     * Delete contact.
     */
    public function deleteContact(int $projectId, int $contactId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);
        $contact = $project->contacts()->findOrFail($contactId);

        // Pivot kapcsolat törlése
        $project->contacts()->detach($contactId);

        // Contact törlése ha nincs más projektje
        if ($contact->projects()->count() === 0) {
            $contact->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen törölve',
        ]);
    }
}

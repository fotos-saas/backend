<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\AddContactRequest;
use App\Http\Requests\Api\Partner\UpdateContactRequest;
use App\Models\TabloContact;
use Illuminate\Http\JsonResponse;

/**
 * Partner Project Contact Controller - Project-specific contact management.
 *
 * Handles: addContact(), updateContact(), deleteContact()
 */
class PartnerProjectContactController extends Controller
{
    use PartnerAuthTrait;

    /**
     * Add contact to project.
     */
    public function addContact(AddContactRequest $request, int $projectId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $project = $this->getProjectForPartner($projectId);

        $isPrimary = $request->boolean('isPrimary', false);

        // Ha ez lesz az elsődleges, többi elsődleges flag törlése a pivot-ban
        if ($isPrimary) {
            $project->contacts()->updateExistingPivot(
                $project->contacts()->pluck('tablo_contacts.id')->toArray(),
                ['is_primary' => false]
            );
        }

        // Create contact with partner_id
        $contact = TabloContact::create([
            'partner_id' => $partnerId,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
        ]);

        // Link to project via pivot
        $contact->projects()->attach($projectId, ['is_primary' => $isPrimary]);

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
        $partnerId = $this->getPartnerIdOrFail();
        $project = $this->getProjectForPartner($projectId);

        // Find contact that is linked to this project and belongs to partner
        $contact = $project->contacts()
            ->where('tablo_contacts.id', $contactId)
            ->where('tablo_contacts.partner_id', $partnerId)
            ->firstOrFail();

        // Ha ez lesz az elsődleges, többi elsődleges flag törlése a pivot-ban
        if ($request->boolean('isPrimary')) {
            // Reset all other contacts' is_primary for this project
            $project->contacts()
                ->where('tablo_contacts.id', '!=', $contactId)
                ->each(fn ($c) => $project->contacts()->updateExistingPivot($c->id, ['is_primary' => false]));
        }

        // Update contact basic fields
        $contact->update([
            'name' => $request->input('name', $contact->name),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
        ]);

        // Update is_primary in pivot if provided
        if ($request->has('isPrimary')) {
            $project->contacts()->updateExistingPivot($contactId, [
                'is_primary' => $request->boolean('isPrimary'),
            ]);
        }

        // Get updated pivot data
        $isPrimary = $project->contacts()
            ->where('tablo_contacts.id', $contactId)
            ->first()?->pivot?->is_primary ?? false;

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
     * Delete contact from project (detach from pivot).
     * Note: This only removes the project-contact link, not the contact itself.
     * If you want to delete the contact entirely, use PartnerContactController::deleteStandaloneContact().
     */
    public function deleteContact(int $projectId, int $contactId): JsonResponse
    {
        $partnerId = $this->getPartnerIdOrFail();
        $project = $this->getProjectForPartner($projectId);

        // Verify contact exists and belongs to partner
        $contact = $project->contacts()
            ->where('tablo_contacts.id', $contactId)
            ->where('tablo_contacts.partner_id', $partnerId)
            ->firstOrFail();

        // Detach from this project (doesn't delete the contact)
        $project->contacts()->detach($contactId);

        // If contact has no more projects, delete it entirely
        if ($contact->projects()->count() === 0) {
            $contact->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen törölve a projektből',
        ]);
    }
}

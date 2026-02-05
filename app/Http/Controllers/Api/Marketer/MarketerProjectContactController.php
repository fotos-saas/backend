<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Requests\Api\Marketer\AddContactRequest;
use App\Http\Requests\Api\Marketer\UpdateContactRequest;
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

        if ($request->boolean('isPrimary')) {
            $project->contacts()->update(['is_primary' => false]);
        }

        $contact = $project->contacts()->create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'is_primary' => $request->boolean('isPrimary', false),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen hozzáadva',
            'data' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'isPrimary' => $contact->is_primary,
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

        if ($request->boolean('isPrimary')) {
            $project->contacts()->where('id', '!=', $contactId)->update(['is_primary' => false]);
        }

        $contact->update([
            'name' => $request->input('name', $contact->name),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'is_primary' => $request->has('isPrimary') ? $request->boolean('isPrimary') : $contact->is_primary,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen frissítve',
            'data' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'isPrimary' => $contact->is_primary,
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

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen törölve',
        ]);
    }
}

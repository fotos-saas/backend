<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tablo\StoreContactRequest;
use App\Http\Requests\Api\Tablo\UpdateContactRequest;
use App\Models\TabloContact;
use App\Models\TabloProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TabloContactController extends Controller
{
    /**
     * List contacts for a project (via pivot table).
     */
    public function index(int $projectId): JsonResponse
    {
        $project = TabloProject::find($projectId);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $contacts = $project->contacts()
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $contacts->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->phone,
                'note' => $c->note,
                'isPrimary' => $c->pivot->is_primary ?? false,
                'created_at' => $c->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Add contact to project.
     */
    public function store(StoreContactRequest $request, int $projectId): JsonResponse
    {
        $project = TabloProject::find($projectId);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        // Create contact with partner_id from project
        $contact = TabloContact::create([
            'partner_id' => $project->partner_id,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'note' => $request->input('note'),
        ]);

        // Link to project via pivot
        $contact->projects()->attach($projectId, ['is_primary' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen hozzáadva',
            'data' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'note' => $contact->note,
                'isPrimary' => false,
                'created_at' => $contact->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Update contact.
     */
    public function update(UpdateContactRequest $request, int $id): JsonResponse
    {
        $contact = TabloContact::find($id);

        if (! $contact) {
            return response()->json([
                'success' => false,
                'message' => 'Kapcsolattartó nem található',
            ], 404);
        }

        $contact->update($request->only(['name', 'email', 'phone', 'note']));

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen frissítve',
            'data' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'note' => $contact->note,
            ],
        ]);
    }

    /**
     * Delete contact.
     */
    public function destroy(int $id): JsonResponse
    {
        $contact = TabloContact::find($id);

        if (! $contact) {
            return response()->json([
                'success' => false,
                'message' => 'Kapcsolattartó nem található',
            ], 404);
        }

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen törölve',
        ]);
    }
}

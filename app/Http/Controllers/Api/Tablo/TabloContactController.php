<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Controller;
use App\Models\TabloContact;
use App\Models\TabloProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
    public function store(Request $request, int $projectId): JsonResponse
    {
        $project = TabloProject::find($projectId);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
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
    public function update(Request $request, int $id): JsonResponse
    {
        $contact = TabloContact::find($id);

        if (! $contact) {
            return response()->json([
                'success' => false,
                'message' => 'Kapcsolattartó nem található',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
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

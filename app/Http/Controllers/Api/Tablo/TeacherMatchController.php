<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Actions\Teacher\MatchTeacherNamesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tablo\MatchTeacherNamesRequest;
use App\Models\TabloProject;
use Illuminate\Http\JsonResponse;

class TeacherMatchController extends Controller
{
    public function matchTeachers(MatchTeacherNamesRequest $request, MatchTeacherNamesAction $action): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id ?? null;

        if (!$projectId) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található.',
            ], 403);
        }

        $project = TabloProject::find($projectId);
        if (!$project || !$project->partner_id) {
            return response()->json([
                'success' => false,
                'message' => 'Partner nem található.',
            ], 403);
        }

        if (!$project->school_id) {
            return response()->json([
                'success' => false,
                'message' => 'Iskola nem található a projekthez.',
            ], 422);
        }

        $result = $action->execute(
            $request->validated('teacher_names'),
            $project->partner_id,
            $project->school_id,
        );

        return response()->json([
            'success' => true,
            ...$result,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Services\ClaudeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerAiSummaryController extends Controller
{
    public function generateSummary(Request $request, ClaudeService $claude): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
        ]);

        $response = $claude->chat(
            userPrompt: $validated['text'],
            systemPrompt: 'Egy fotós stúdió mintacsomag leírásából készíts rövid, tömör magyar nyelvű összefoglalót a szülők/diákok számára. Az összefoglaló legyen 2-3 mondat, köznyelvi, barátságos hangvételű. Ne használj markdown formázást, csak egyszerű szöveget adj vissza.',
            options: [
                'model' => 'claude-haiku-4-20250514',
                'max_tokens' => 300,
                'temperature' => 0.7,
            ]
        );

        return response()->json([
            'success' => true,
            'summary' => trim($response['content']),
        ]);
    }
}

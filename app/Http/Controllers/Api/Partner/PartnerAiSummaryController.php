<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\GenerateAiSummaryRequest;
use App\Services\ClaudeService;
use Illuminate\Http\JsonResponse;

class PartnerAiSummaryController extends Controller
{
    public function generateSummary(GenerateAiSummaryRequest $request, ClaudeService $claude): JsonResponse
    {
        $validated = $request->validated();

        try {
            $response = $claude->chat(
                userPrompt: $validated['text'],
                systemPrompt: <<<'PROMPT'
Egy tablófotós stúdió adminisztrátoraként dolgozol. A beérkező szöveget (ami lehet email, üzenet, leírás, megrendelői kérés) feladatpontokra kell bontanod.

SZABÁLYOK:
- Minden konkrét teendőt külön pontba szedj
- Rövid, tömör, cselekvő megfogalmazás (pl. "Háttérkép cseréje a csatolt fotók alapján")
- Magyar nyelven válaszolj
- NE használj markdown formázást, csak egyszerű számozott listát (1. 2. 3.)
- Hagyd ki a köszönéseket, aláírásokat, udvariassági formulákat
- Ha a szövegből nem derül ki konkrét feladat, írd: "Nem található konkrét feladat a szövegben."
- Maximum 10 feladatpont
PROMPT,
                options: [
                    'model' => 'claude-sonnet-4-5-20250929',
                    'max_tokens' => 500,
                    'temperature' => 0.3,
                ]
            );

            return response()->json([
                'success' => true,
                'summary' => trim($response['content']),
            ]);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Az AI összefoglaló generálás nem sikerült.',
            ], 500);
        }
    }
}

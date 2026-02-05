<?php

namespace App\Actions\Client;

use App\Models\PartnerAlbum;
use App\Models\PartnerAlbumProgress;
use App\Models\PartnerClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaveSimpleSelectionAction
{
    public function execute(Request $request, PartnerAlbum $album, PartnerClient $client): JsonResponse
    {
        $validated = $request->validate([
            'selected_ids' => ['required', 'array'],
            'selected_ids.*' => ['required', 'integer'],
            'finalize' => ['boolean'],
        ]);

        $selectedIds = array_map('intval', $validated['selected_ids']);
        $finalize = $validated['finalize'] ?? false;

        if ($finalize) {
            if ($album->min_selections && count($selectedIds) < $album->min_selections) {
                return response()->json([
                    'success' => false,
                    'message' => "Minimum {$album->min_selections} képet kell kiválasztanod.",
                ], 422);
            }
        }

        if ($album->max_selections && count($selectedIds) > $album->max_selections) {
            return response()->json([
                'success' => false,
                'message' => "Maximum {$album->max_selections} képet választhatsz ki.",
            ], 422);
        }

        $albumMediaIds = $album->getMedia('photos')->pluck('id')->toArray();
        $invalidIds = array_diff($selectedIds, $albumMediaIds);

        if (!empty($invalidIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Néhány kiválasztott kép nem tartozik ehhez az albumhoz.',
            ], 422);
        }

        $progress = PartnerAlbumProgress::firstOrCreate(
            [
                'partner_album_id' => $album->id,
                'partner_client_id' => $client->id,
            ],
            [
                'current_step' => PartnerAlbumProgress::STEP_CLAIMING,
                'steps_data' => PartnerAlbumProgress::getDefaultStepsData(),
            ]
        );

        $progress->setClaimedIds($selectedIds);

        if ($finalize) {
            $album->finalize();
        }

        return response()->json([
            'success' => true,
            'message' => $finalize ? 'Választás sikeresen véglegesítve!' : 'Választás mentve.',
            'data' => [
                'selectedCount' => count($selectedIds),
                'isCompleted' => $album->fresh()->isCompleted(),
            ],
        ]);
    }
}

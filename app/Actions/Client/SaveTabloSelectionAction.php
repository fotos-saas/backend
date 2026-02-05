<?php

namespace App\Actions\Client;

use App\Models\PartnerAlbum;
use App\Models\PartnerAlbumProgress;
use App\Models\PartnerClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaveTabloSelectionAction
{
    public function execute(Request $request, PartnerAlbum $album, PartnerClient $client): JsonResponse
    {
        $validated = $request->validate([
            'step' => ['required', 'string', 'in:claiming,retouch,tablo'],
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer'],
            'finalize' => ['boolean'],
        ]);

        $step = $validated['step'];
        $ids = array_map('intval', $validated['ids']);
        $finalize = $validated['finalize'] ?? false;

        $albumMediaIds = $album->getMedia('photos')->pluck('id')->toArray();
        $invalidIds = array_diff($ids, $albumMediaIds);

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

        $stepOrder = ['claiming', 'retouch', 'tablo'];
        $currentStepIndex = array_search($progress->current_step, $stepOrder);
        $requestedStepIndex = array_search($step, $stepOrder);

        if ($requestedStepIndex > $currentStepIndex + 1) {
            return response()->json([
                'success' => false,
                'message' => 'Először az előző lépést kell befejezned.',
            ], 422);
        }

        $result = $this->processStep($step, $ids, $finalize, $album, $progress);
        if ($result !== null) {
            return $result;
        }

        if ($requestedStepIndex > $currentStepIndex) {
            $progress->update(['current_step' => $step]);
        }

        $statusMap = [
            'claiming' => PartnerAlbum::STATUS_CLAIMING,
            'retouch' => PartnerAlbum::STATUS_RETOUCH,
            'tablo' => PartnerAlbum::STATUS_TABLO,
        ];
        if ($album->status !== $statusMap[$step]) {
            $album->update(['status' => $statusMap[$step]]);
        }

        if ($finalize && $step === 'tablo') {
            $album->finalize();
        }

        return response()->json([
            'success' => true,
            'message' => $finalize ? 'Választás sikeresen véglegesítve!' : 'Lépés mentve.',
            'data' => [
                'currentStep' => $progress->current_step,
                'percentage' => $progress->getProgressPercentage(),
                'isCompleted' => $album->fresh()->isCompleted(),
            ],
        ]);
    }

    private function processStep(string $step, array $ids, bool $finalize, PartnerAlbum $album, PartnerAlbumProgress $progress): ?JsonResponse
    {
        switch ($step) {
            case 'claiming':
                if ($finalize && $album->min_selections && count($ids) < $album->min_selections) {
                    return response()->json([
                        'success' => false,
                        'message' => "Minimum {$album->min_selections} képet kell kiválasztanod.",
                    ], 422);
                }
                if ($album->max_selections && count($ids) > $album->max_selections) {
                    return response()->json([
                        'success' => false,
                        'message' => "Maximum {$album->max_selections} képet választhatsz ki.",
                    ], 422);
                }
                $progress->setClaimedIds($ids);
                break;

            case 'retouch':
                $claimedIds = $progress->getClaimedIds();
                $invalidRetouch = array_diff($ids, $claimedIds);
                if (!empty($invalidRetouch)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Retusra csak a kiválasztott képeket jelölheted.',
                    ], 422);
                }
                if ($album->max_retouch_photos && count($ids) > $album->max_retouch_photos) {
                    return response()->json([
                        'success' => false,
                        'message' => "Maximum {$album->max_retouch_photos} képet jelölhetsz retusra.",
                    ], 422);
                }
                $progress->setRetouchIds($ids);
                break;

            case 'tablo':
                if (count($ids) !== 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Pontosan egy tablóképet kell választanod.',
                    ], 422);
                }
                $claimedIds = $progress->getClaimedIds();
                if (!in_array($ids[0], $claimedIds)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A tablókép csak a kiválasztott képek közül választható.',
                    ], 422);
                }
                $progress->setTabloId($ids[0]);
                break;
        }

        return null;
    }
}

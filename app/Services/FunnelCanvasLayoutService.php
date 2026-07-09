<?php

namespace App\Services;

use App\Models\Funnel;
use App\Models\FunnelStage;

class FunnelCanvasLayoutService
{
    private const START_X = 80;

    private const START_Y = 200;

    private const GAP_X = 300;

    public function ensureStagePositions(Funnel $funnel): void
    {
        $stages = $funnel->stages()->orderBy('sort_order')->orderBy('id')->get();

        foreach ($stages as $index => $stage) {
            if ($stage->canvas_pos_x === null || $stage->canvas_pos_y === null) {
                $stage->update([
                    'canvas_pos_x' => self::START_X + ($index * self::GAP_X),
                    'canvas_pos_y' => self::START_Y,
                ]);
            }
        }
    }

    public function repositionStage(FunnelStage $stage, int $posX, int $posY): void
    {
        $stage->update([
            'canvas_pos_x' => max(0, $posX),
            'canvas_pos_y' => max(0, $posY),
        ]);
    }
}

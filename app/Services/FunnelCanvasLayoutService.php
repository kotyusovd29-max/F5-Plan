<?php

namespace App\Services;

use App\Models\Funnel;
use App\Models\FunnelStage;

class FunnelCanvasLayoutService
{
    private const START_X = 30;

    private const START_Y = 50;

    private const GAP_X = 230;

    private const GAP_Y = 200;

    private const COLS = 4;

    public function ensureStagePositions(Funnel $funnel): void
    {
        $stages = $funnel->stages()->orderBy('sort_order')->orderBy('id')->get();

        foreach ($stages as $index => $stage) {
            if ($stage->canvas_pos_x === null || $stage->canvas_pos_y === null) {
                [$x, $y] = $this->snakePosition($index);

                $stage->update([
                    'canvas_pos_x' => $x,
                    'canvas_pos_y' => $y,
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

    /**
     * Snake layout similar to Miro example: L→R, then R→L on next row.
     *
     * @return array{0: int, 1: int}
     */
    private function snakePosition(int $index): array
    {
        $row = intdiv($index, self::COLS);
        $colInRow = $index % self::COLS;
        $col = $row % 2 === 0 ? $colInRow : (self::COLS - 1 - $colInRow);

        return [
            self::START_X + ($col * self::GAP_X),
            self::START_Y + ($row * self::GAP_Y),
        ];
    }
}

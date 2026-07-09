<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateFunnelCanvasLayoutRequest;
use App\Models\Funnel;
use App\Models\Project;
use App\Services\FunnelCanvasLayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class FunnelCanvasController extends Controller
{
    public function show(
        Project $project,
        Funnel $funnel,
        FunnelCanvasLayoutService $layoutService
    ): View {
        abort_unless($funnel->project_id === $project->id, 404);

        $funnel->load('stages');
        $layoutService->ensureStagePositions($funnel);
        $funnel->load('stages');

        return view('funnels.canvas.show', compact('project', 'funnel'));
    }

    public function refresh(
        Project $project,
        Funnel $funnel,
        FunnelCanvasLayoutService $layoutService
    ): JsonResponse {
        abort_unless($funnel->project_id === $project->id, 404);

        $funnel->load('stages');
        $layoutService->ensureStagePositions($funnel);
        $funnel->load('stages');

        return response()->json([
            'html' => view('funnels.canvas._surface', compact('project', 'funnel'))->render(),
            'title' => $funnel->name,
        ]);
    }

    public function update(
        UpdateFunnelCanvasLayoutRequest $request,
        Project $project,
        Funnel $funnel
    ): JsonResponse {
        abort_unless($funnel->project_id === $project->id, 404);

        if ($request->has('stages')) {
            $stageIds = $funnel->stages()->pluck('id');

            foreach ($request->validated('stages') as $stageData) {
                if (! $stageIds->contains($stageData['id'])) {
                    abort(403);
                }

                $funnel->stages()
                    ->where('id', $stageData['id'])
                    ->update([
                        'canvas_pos_x' => $stageData['canvas_pos_x'],
                        'canvas_pos_y' => $stageData['canvas_pos_y'],
                    ]);
            }
        }

        if ($request->has('viewport')) {
            $viewport = $request->validated('viewport');

            $funnel->update([
                'canvas_offset_x' => $viewport['offset_x'],
                'canvas_offset_y' => $viewport['offset_y'],
                'canvas_zoom' => $viewport['zoom'],
            ]);
        }

        return response()->json(['success' => true]);
    }
}

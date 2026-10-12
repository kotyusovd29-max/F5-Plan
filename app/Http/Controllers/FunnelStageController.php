<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsForCanvas;
use App\Http\Requests\ReorderItemsRequest;
use App\Http\Requests\StoreFunnelStageRequest;
use App\Http\Requests\UpdateFunnelStageRequest;
use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Services\FunnelCanvasLayoutService;
use App\Services\SortOrderService;
use App\Support\FunnelStageColors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FunnelStageController extends Controller
{
    use RespondsForCanvas;
    public function store(
        StoreFunnelStageRequest $request,
        Project $project,
        Funnel $funnel
    ): RedirectResponse|JsonResponse {
        abort_unless($funnel->project_id === $project->id, 404);

        $afterStageId = $request->validated('after_stage_id');

        if ($afterStageId) {
            $afterStage = $funnel->stages()->findOrFail($afterStageId);
            $sortOrder = $afterStage->sort_order + 1;

            $funnel->stages()
                ->where('sort_order', '>=', $sortOrder)
                ->increment('sort_order');
        } else {
            $sortOrder = ($funnel->stages()->max('sort_order') ?? 0) + 1;
        }

        $existingStagesCount = $funnel->stages()->count();
        $color = $request->validated('color')
            ?? FunnelStageColors::defaultForIndex($existingStagesCount);

        $funnel->stages()->create([
            ...$request->stageAttributes(),
            'color' => $color,
            'sort_order' => $sortOrder,
            'canvas_pos_x' => null,
            'canvas_pos_y' => null,
        ]);

        app(FunnelCanvasLayoutService::class)->ensureStagePositions($funnel->fresh(['stages']));

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.funnels.show', [$project, $funnel])
                ->with('success', 'Этап создан.'),
            'Этап создан.'
        );
    }

    public function update(
        UpdateFunnelStageRequest $request,
        Project $project,
        Funnel $funnel,
        FunnelStage $stage
    ): RedirectResponse|JsonResponse {
        abort_unless($funnel->project_id === $project->id, 404);
        abort_unless($stage->funnel_id === $funnel->id, 404);

        $stage->update($request->stageAttributes());

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.funnels.show', [$project, $funnel])
                ->with('success', 'Этап сохранён.'),
            'Этап сохранён.'
        );
    }

    public function destroy(Request $request, Project $project, Funnel $funnel, FunnelStage $stage): RedirectResponse|JsonResponse
    {
        abort_unless($funnel->project_id === $project->id, 404);
        abort_unless($stage->funnel_id === $funnel->id, 404);

        $deletedOrder = $stage->sort_order;
        $stage->delete();

        $funnel->stages()
            ->where('sort_order', '>', $deletedOrder)
            ->decrement('sort_order');

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.funnels.show', [$project, $funnel])
                ->with('success', 'Этап удалён.'),
            'Этап удалён.'
        );
    }

    public function reorder(
        ReorderItemsRequest $request,
        Project $project,
        Funnel $funnel,
        SortOrderService $sortOrderService
    ): JsonResponse {
        abort_unless($funnel->project_id === $project->id, 404);

        $sortOrderService->reorder(
            $funnel->stages(),
            $request->validated('order')
        );

        return response()->json(['success' => true]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsForCanvas;
use App\Http\Requests\ReorderItemsRequest;
use App\Http\Requests\StoreProjectWorkStageRequest;
use App\Http\Requests\UpdateProjectWorkStageRequest;
use App\Models\Project;
use App\Models\ProjectWorkStage;
use App\Services\SortOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectWorkStageController extends Controller
{
    use RespondsForCanvas;

    public function store(StoreProjectWorkStageRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $sortOrder = ($project->workStages()->max('sort_order') ?? 0) + 1;

        $project->workStages()->create([
            ...$request->stagePayload(),
            'sort_order' => $sortOrder,
        ]);

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.show', $project)
                ->with('success', 'Этап работ добавлен.'),
            'Этап работ добавлен.'
        );
    }

    public function update(
        UpdateProjectWorkStageRequest $request,
        Project $project,
        ProjectWorkStage $workStage
    ): RedirectResponse|JsonResponse {
        abort_unless($workStage->project_id === $project->id, 404);

        $workStage->update($request->stagePayload());

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.show', $project)
                ->with('success', 'Этап работ сохранён.'),
            'Этап работ сохранён.'
        );
    }

    public function destroy(Request $request, Project $project, ProjectWorkStage $workStage): RedirectResponse|JsonResponse
    {
        abort_unless($workStage->project_id === $project->id, 404);

        $deletedOrder = $workStage->sort_order;
        $workStage->delete();

        $project->workStages()
            ->where('sort_order', '>', $deletedOrder)
            ->decrement('sort_order');

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.show', $project)
                ->with('success', 'Этап работ удалён.'),
            'Этап работ удалён.'
        );
    }

    public function reorder(
        ReorderItemsRequest $request,
        Project $project,
        SortOrderService $sortOrderService
    ): JsonResponse {
        $sortOrderService->reorder(
            $project->workStages(),
            $request->validated('order')
        );

        return response()->json(['success' => true]);
    }
}

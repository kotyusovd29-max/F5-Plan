<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsForCanvas;
use App\Http\Requests\ReorderItemsRequest;
use App\Http\Requests\StoreProjectIntegrationRequest;
use App\Http\Requests\UpdateProjectIntegrationRequest;
use App\Models\Project;
use App\Models\ProjectIntegration;
use App\Services\SortOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectIntegrationController extends Controller
{
    use RespondsForCanvas;
    public function store(StoreProjectIntegrationRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $sortOrder = ($project->integrations()->max('sort_order') ?? 0) + 1;

        $integration = $project->integrations()->create([
            ...$request->safe()->only(['name', 'comment']),
            'sort_order' => $sortOrder,
        ]);

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.show', $project)
                ->with('success', 'Интеграция добавлена.'),
            'Интеграция добавлена.'
        );
    }

    public function update(
        UpdateProjectIntegrationRequest $request,
        Project $project,
        ProjectIntegration $integration
    ): RedirectResponse|JsonResponse {
        abort_unless($integration->project_id === $project->id, 404);

        $integration->update($request->validated());

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.show', $project)
                ->with('success', 'Интеграция сохранена.'),
            'Интеграция сохранена.'
        );
    }

    public function destroy(Request $request, Project $project, ProjectIntegration $integration): RedirectResponse|JsonResponse
    {
        abort_unless($integration->project_id === $project->id, 404);

        $deletedOrder = $integration->sort_order;
        $integration->delete();

        $project->integrations()
            ->where('sort_order', '>', $deletedOrder)
            ->decrement('sort_order');

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.show', $project)
                ->with('success', 'Интеграция удалена.'),
            'Интеграция удалена.'
        );
    }

    public function reorder(
        ReorderItemsRequest $request,
        Project $project,
        SortOrderService $sortOrderService
    ): JsonResponse {
        $sortOrderService->reorder(
            $project->integrations(),
            $request->validated('order')
        );

        return response()->json(['success' => true]);
    }
}

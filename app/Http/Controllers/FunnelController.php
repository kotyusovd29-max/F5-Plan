<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsForCanvas;
use App\Http\Requests\StoreFunnelRequest;
use App\Http\Requests\UpdateFunnelRequest;
use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Services\CanvasLayoutService;
use App\Support\F5WidgetCatalog;
use App\Support\FunnelStageColors;
use App\Support\FunnelStageWidgets;
use App\Support\ProjectAccessPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FunnelController extends Controller
{
    use RespondsForCanvas;

    public function show(Project $project, Funnel $funnel): Response
    {
        abort_unless($funnel->project_id === $project->id, 404);

        $funnel->load('stages');

        return Inertia::render('Funnels/Show', [
            'project' => [
                'id' => $project->id,
                'company_name' => $project->company_name,
                ...ProjectAccessPresenter::forUser($project, request()->user()),
                'urls' => [
                    'show' => route('projects.show', $project),
                ],
            ],
            'funnel' => $this->serializeFunnel($project, $funnel),
            'colorPalette' => FunnelStageColors::palette(),
            'f5WidgetCatalog' => F5WidgetCatalog::forFrontend(),
        ]);
    }

    public function store(StoreFunnelRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $sortOrder = ($project->funnels()->max('sort_order') ?? 0) + 1;

        $funnel = $project->funnels()->create([
            'name' => $request->validated('name'),
            'sort_order' => $sortOrder,
        ]);

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.funnels.show', [$project, $funnel])
                ->with('success', 'Воронка создана.'),
            'Воронка создана.'
        );
    }

    public function update(UpdateFunnelRequest $request, Project $project, Funnel $funnel): RedirectResponse|JsonResponse
    {
        abort_unless($funnel->project_id === $project->id, 404);

        $funnel->update($request->validated());

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.funnels.show', [$project, $funnel])
                ->with('success', 'Информация о воронке сохранена.'),
            'Информация о воронке сохранена.'
        );
    }

    public function destroy(
        Request $request,
        Project $project,
        Funnel $funnel,
        CanvasLayoutService $canvasLayoutService
    ): RedirectResponse|JsonResponse {
        abort_unless($funnel->project_id === $project->id, 404);

        $deletedOrder = $funnel->sort_order;
        $funnel->delete();

        $project->funnels()
            ->where('sort_order', '>', $deletedOrder)
            ->decrement('sort_order');

        $canvasLayoutService->syncBlocks($project);

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.show', $project)
                ->with('success', 'Воронка удалена.'),
            'Воронка удалена.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeFunnel(Project $project, Funnel $funnel): array
    {
        return [
            'id' => $funnel->id,
            'name' => $funnel->name,
            'description' => $funnel->description,
            'stages_count' => $funnel->stages->count(),
            'urls' => [
                'update' => route('projects.funnels.update', [$project, $funnel]),
                'destroy' => route('projects.funnels.destroy', [$project, $funnel]),
                'canvas' => route('projects.funnels.canvas.show', [$project, $funnel]),
                'stages_store' => route('projects.funnels.stages.store', [$project, $funnel]),
                'stages_reorder' => route('projects.funnels.stages.reorder', [$project, $funnel]),
            ],
            'stages' => $funnel->stages->values()->map(
                fn (FunnelStage $stage, int $index) => $this->serializeStage($project, $funnel, $stage, $index + 1)
            )->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeStage(Project $project, Funnel $funnel, FunnelStage $stage, int $number): array
    {
        return [
            'id' => $stage->id,
            'number' => $number,
            'name' => $stage->name,
            'action' => $stage->action,
            'comment' => $stage->comment,
            'stage_type' => $stage->stage_type,
            'deadline' => $stage->deadline,
            'kind' => $stage->kind ?: 'normal',
            'outcomes' => is_array($stage->outcomes) ? $stage->outcomes : [],
            'widgets' => FunnelStageWidgets::present(
                is_array($stage->widgets) ? $stage->widgets : []
            ),
            'color' => $stage->color ?? $stage->barColor(),
            'bar_color' => $stage->barColor(),
            'urls' => [
                'update' => route('projects.funnels.stages.update', [$project, $funnel, $stage]),
                'destroy' => route('projects.funnels.stages.destroy', [$project, $funnel, $stage]),
            ],
        ];
    }
}

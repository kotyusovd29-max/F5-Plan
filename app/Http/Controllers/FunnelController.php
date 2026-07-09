<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsForCanvas;
use App\Http\Requests\StoreFunnelRequest;
use App\Http\Requests\UpdateFunnelRequest;
use App\Models\Funnel;
use App\Models\Project;
use App\Services\CanvasLayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FunnelController extends Controller
{
    use RespondsForCanvas;
    public function show(Project $project, Funnel $funnel): View
    {
        abort_unless($funnel->project_id === $project->id, 404);

        $funnel->load('stages');

        return view('funnels.show', compact('project', 'funnel'));
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
}

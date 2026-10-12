<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsForCanvas;
use App\Http\Requests\ReorderSkkFieldsRequest;
use App\Http\Requests\StoreProjectSkkFieldRequest;
use App\Http\Requests\UpdateProjectSkkFieldRequest;
use App\Models\Project;
use App\Models\ProjectSkkField;
use App\Services\SortOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectSkkFieldController extends Controller
{
    use RespondsForCanvas;
    public function store(StoreProjectSkkFieldRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $entityType = $request->validated('entity_type');

        $sortOrder = ($project->skkFields()
            ->where('entity_type', $entityType)
            ->max('sort_order') ?? 0) + 1;

        $field = $project->skkFields()->create([
            ...ProjectSkkField::normalizeAttributes($request->validated()),
            'entity_type' => $entityType,
            'sort_order' => $sortOrder,
        ]);

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.show', $project)
                ->with('success', 'Поле добавлено.'),
            'Поле добавлено.'
        );
    }

    public function update(
        UpdateProjectSkkFieldRequest $request,
        Project $project,
        ProjectSkkField $skkField
    ): RedirectResponse|JsonResponse {
        abort_unless($skkField->project_id === $project->id, 404);

        $skkField->update(ProjectSkkField::normalizeAttributes($request->validated()));

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.show', $project)
                ->with('success', 'Поле сохранено.'),
            'Поле сохранено.'
        );
    }

    public function destroy(Request $request, Project $project, ProjectSkkField $skkField): RedirectResponse|JsonResponse
    {
        abort_unless($skkField->project_id === $project->id, 404);

        $entityType = $skkField->entity_type;
        $deletedOrder = $skkField->sort_order;
        $skkField->delete();

        $project->skkFields()
            ->where('entity_type', $entityType)
            ->where('sort_order', '>', $deletedOrder)
            ->decrement('sort_order');

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.show', $project)
                ->with('success', 'Поле удалено.'),
            'Поле удалено.'
        );
    }

    public function reorder(
        ReorderSkkFieldsRequest $request,
        Project $project,
        SortOrderService $sortOrderService
    ): JsonResponse {
        $entityType = $request->validated('entity_type');

        $sortOrderService->reorder(
            $project->skkFields()->where('entity_type', $entityType),
            $request->validated('order')
        );

        return response()->json(['success' => true]);
    }
}

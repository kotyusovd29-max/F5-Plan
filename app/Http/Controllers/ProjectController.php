<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsForCanvas;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Services\ProjectDocxExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\View\View;

class ProjectController extends Controller
{
    use RespondsForCanvas;
    public function create(): View
    {
        return view('projects.create');
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $request->user()->projects()->create($request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Проект создан.');
    }

    public function show(Project $project): View
    {
        $project->load([
            'user',
            'funnels' => fn ($query) => $query->withCount('stages'),
            'integrations',
            'skkFields',
        ]);

        return view('projects.show', compact('project'));
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $project->update($request->validated());

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.show', $project)
                ->with('success', 'Информация о проекте сохранена.'),
            'Информация о проекте сохранена.'
        );
    }

    public function exportDocx(Project $project, ProjectDocxExporter $exporter): BinaryFileResponse
    {
        $tempFile = $exporter->build($project);

        return response()
            ->download($tempFile, $exporter->filename($project))
            ->deleteFileAfterSend();
    }
}

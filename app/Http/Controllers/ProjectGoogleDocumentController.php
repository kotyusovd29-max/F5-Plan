<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectGoogleDocument;
use App\Support\ProjectAccessPresenter;
use App\Support\ProjectAiUrls;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectGoogleDocumentController extends Controller
{
    public function index(Project $project): Response
    {
        $project->loadMissing('user');

        $documents = $project->googleDocuments()
            ->with('user:id,name')
            ->latest()
            ->get()
            ->map(fn (ProjectGoogleDocument $document) => [
                'id' => $document->id,
                'google_file_id' => $document->google_file_id,
                'name' => $document->name,
                'url' => $document->url,
                'source' => $document->source,
                'source_label' => $document->sourceLabel(),
                'author' => $document->user?->name,
                'created_at' => $document->created_at?->format('d.m.Y H:i'),
            ])
            ->values()
            ->all();

        return Inertia::render('Projects/Documents', [
            'project' => [
                'id' => $project->id,
                'company_name' => $project->company_name,
                ...ProjectAccessPresenter::forUser($project, auth()->user()),
                'urls' => [
                    'home' => route('home'),
                    'show' => route('projects.show', $project),
                    'export_docx' => route('projects.export.docx', $project),
                    'export_google_docs' => route('projects.export.google-docs', $project),
                    'documents' => route('projects.documents.index', $project),
                    'canvas' => route('projects.canvas.show', $project),
                    ...ProjectAiUrls::forProject($project),
                ],
            ],
            'documents' => $documents,
            'googleConnected' => (bool) auth()->user()?->hasGoogleConnected(),
            'googleConnectUrl' => route('google.connect'),
        ]);
    }

    public function destroy(Project $project, ProjectGoogleDocument $document): RedirectResponse
    {
        abort_unless($document->project_id === $project->id, 404);

        $document->delete();

        return redirect()
            ->route('projects.documents.index', $project)
            ->with('success', 'Документ убран из списка проекта. Файл в Google Drive сохранён.');
    }
}

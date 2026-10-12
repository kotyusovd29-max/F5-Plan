<?php

namespace App\Http\Controllers;

use App\Models\LibraryFunnel;
use App\Models\LibrarySkkField;
use App\Models\Project;
use App\Services\LibraryTemplateApplier;
use Illuminate\Http\RedirectResponse;

class ProjectLibraryController extends Controller
{
    public function applyFunnel(
        Project $project,
        LibraryFunnel $libraryFunnel,
        LibraryTemplateApplier $applier,
    ): RedirectResponse {
        $applier->applyFunnel($project, $libraryFunnel);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', "Воронка «{$libraryFunnel->name}» добавлена из библиотеки.");
    }

    public function applySkkField(
        Project $project,
        LibrarySkkField $librarySkkField,
        LibraryTemplateApplier $applier,
    ): RedirectResponse {
        $applier->applySkkField($project, $librarySkkField);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', "Поле «{$librarySkkField->name}» добавлено из библиотеки.");
    }
}

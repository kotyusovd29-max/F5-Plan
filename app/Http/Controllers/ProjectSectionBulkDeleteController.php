<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsForCanvas;
use App\Models\Project;
use App\Models\ProjectSkkField;
use App\Services\ProjectSectionBulkDeleteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectSectionBulkDeleteController extends Controller
{
    use RespondsForCanvas;

    public function destroySkkSection(
        Request $request,
        Project $project,
        string $entityType,
        ProjectSectionBulkDeleteService $bulkDeleteService
    ): RedirectResponse|JsonResponse {
        abort_unless(in_array($entityType, ProjectSkkField::TYPES, true), 404);

        $message = $bulkDeleteService->deleteSkkSection($project, $entityType);

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.show', $project)
                ->with('success', $message),
            $message
        );
    }

    public function destroyAllIntegrations(
        Request $request,
        Project $project,
        ProjectSectionBulkDeleteService $bulkDeleteService
    ): RedirectResponse|JsonResponse {
        $message = $bulkDeleteService->deleteAllIntegrations($project);

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.show', $project)
                ->with('success', $message),
            $message
        );
    }

    public function destroyAllWorkStages(
        Request $request,
        Project $project,
        ProjectSectionBulkDeleteService $bulkDeleteService
    ): RedirectResponse|JsonResponse {
        $message = $bulkDeleteService->deleteAllWorkStages($project);

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.show', $project)
                ->with('success', $message),
            $message
        );
    }

    public function destroyAllFunnels(
        Request $request,
        Project $project,
        ProjectSectionBulkDeleteService $bulkDeleteService
    ): RedirectResponse|JsonResponse {
        $message = $bulkDeleteService->deleteAllFunnels($project);

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.show', $project)
                ->with('success', $message),
            $message
        );
    }
}

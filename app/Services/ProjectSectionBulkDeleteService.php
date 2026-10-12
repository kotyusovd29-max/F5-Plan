<?php

namespace App\Services;

use App\Models\Funnel;
use App\Models\Project;
use App\Models\ProjectIntegration;
use App\Models\ProjectSkkField;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProjectSectionBulkDeleteService
{
    public function __construct(
        private readonly CanvasLayoutService $canvasLayoutService
    ) {}

    public function deleteSkkSection(Project $project, string $entityType): string
    {
        if (! in_array($entityType, ProjectSkkField::TYPES, true)) {
            throw new InvalidArgumentException('Неизвестный раздел полей.');
        }

        $count = $project->skkFields()->where('entity_type', $entityType)->count();

        $project->skkFields()->where('entity_type', $entityType)->delete();

        $this->canvasLayoutService->syncBlocks($project->fresh());

        return $count > 0
            ? "Удалено полей: {$count}."
            : 'Раздел полей очищен.';
    }

    public function deleteAllIntegrations(Project $project): string
    {
        $count = $project->integrations()->count();

        $project->integrations()->each(function (ProjectIntegration $integration) {
            $integration->delete();
        });

        $this->canvasLayoutService->syncBlocks($project->fresh());

        return $count > 0
            ? "Удалено интеграций: {$count}."
            : 'Раздел интеграций очищен.';
    }

    public function deleteAllWorkStages(Project $project): string
    {
        $count = $project->workStages()->count();

        $project->workStages()->delete();

        return $count > 0
            ? "Удалено этапов работ: {$count}."
            : 'Раздел этапов работ очищен.';
    }

    public function deleteAllFunnels(Project $project): string
    {
        $count = $project->funnels()->count();

        DB::transaction(function () use ($project) {
            $project->funnels()->each(function (Funnel $funnel) {
                $funnel->delete();
            });
        });

        $this->canvasLayoutService->syncBlocks($project->fresh());

        return $count > 0
            ? "Удалено воронок: {$count}."
            : 'Воронки удалены.';
    }

    public function deleteFunnel(Project $project, Funnel $funnel): string
    {
        abort_unless($funnel->project_id === $project->id, 404);

        $name = $funnel->name;
        $deletedOrder = $funnel->sort_order;
        $funnel->delete();

        $project->funnels()
            ->where('sort_order', '>', $deletedOrder)
            ->decrement('sort_order');

        $this->canvasLayoutService->syncBlocks($project->fresh());

        return "Воронка «{$name}» удалена.";
    }
}

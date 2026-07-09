<?php

namespace App\Services;

use App\Models\Funnel;
use App\Models\Project;
use App\Models\ProjectCanvasBlock;
use App\Models\ProjectIntegration;
use App\Models\ProjectSkkField;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CanvasBlockDeletionService
{
    public function __construct(
        private readonly CanvasLayoutService $canvasLayoutService
    ) {}

    public function delete(Project $project, ProjectCanvasBlock $block): string
    {
        abort_unless($block->project_id === $project->id, 404);

        $message = DB::transaction(function () use ($project, $block) {
            return match ($block->block_type) {
                ProjectCanvasBlock::TYPE_SKK => $this->deleteSkkBlock($project, $block),
                ProjectCanvasBlock::TYPE_INTEGRATIONS => $this->deleteIntegrationsBlock($project),
                ProjectCanvasBlock::TYPE_FUNNEL => $this->deleteFunnelBlock($project, $block),
                default => throw new InvalidArgumentException('Этот блок нельзя удалить.'),
            };
        });

        $this->canvasLayoutService->syncBlocks($project->fresh());

        return $message;
    }

    public function isDeletable(ProjectCanvasBlock $block): bool
    {
        return in_array($block->block_type, [
            ProjectCanvasBlock::TYPE_SKK,
            ProjectCanvasBlock::TYPE_INTEGRATIONS,
            ProjectCanvasBlock::TYPE_FUNNEL,
        ], true);
    }

    private function deleteSkkBlock(Project $project, ProjectCanvasBlock $block): string
    {
        $entityType = $this->canvasLayoutService->entityTypeForBlock($block);

        if ($entityType === null || ! in_array($entityType, ProjectSkkField::TYPES, true)) {
            throw new InvalidArgumentException('Не удалось определить раздел полей.');
        }

        $count = $project->skkFields()->where('entity_type', $entityType)->count();
        $project->skkFields()->where('entity_type', $entityType)->delete();

        return $count > 0
            ? 'Поля удалены.'
            : 'Раздел полей очищен.';
    }

    private function deleteIntegrationsBlock(Project $project): string
    {
        $count = $project->integrations()->count();
        $project->integrations()->each(function (ProjectIntegration $integration) {
            $integration->delete();
        });

        return $count > 0
            ? 'Интеграции удалены.'
            : 'Раздел интеграций очищен.';
    }

    private function deleteFunnelBlock(Project $project, ProjectCanvasBlock $block): string
    {
        $funnel = $project->funnels()->find($block->reference_id);

        if (! $funnel) {
            return 'Воронка уже удалена.';
        }

        $name = $funnel->name;
        $deletedOrder = $funnel->sort_order;
        $funnel->delete();

        $project->funnels()
            ->where('sort_order', '>', $deletedOrder)
            ->decrement('sort_order');

        return "Воронка «{$name}» удалена.";
    }
}

<?php

namespace App\Services;

use App\Models\Funnel;
use App\Models\Project;
use App\Models\ProjectCanvasBlock;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CanvasBlockDeletionService
{
    public function __construct(
        private readonly ProjectSectionBulkDeleteService $bulkDeleteService
    ) {}

    public function delete(Project $project, ProjectCanvasBlock $block): string
    {
        abort_unless($block->project_id === $project->id, 404);

        return DB::transaction(function () use ($project, $block) {
            return match ($block->block_type) {
                ProjectCanvasBlock::TYPE_SKK => $this->deleteSkkBlock($project, $block),
                ProjectCanvasBlock::TYPE_INTEGRATIONS => $this->bulkDeleteService->deleteAllIntegrations($project),
                ProjectCanvasBlock::TYPE_FUNNEL => $this->deleteFunnelBlock($project, $block),
                default => throw new InvalidArgumentException('Этот блок нельзя удалить.'),
            };
        });
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
        $entityType = app(CanvasLayoutService::class)->entityTypeForBlock($block);

        if ($entityType === null) {
            throw new InvalidArgumentException('Не удалось определить раздел полей.');
        }

        return $this->bulkDeleteService->deleteSkkSection($project, $entityType);
    }

    private function deleteFunnelBlock(Project $project, ProjectCanvasBlock $block): string
    {
        $funnel = $project->funnels()->find($block->reference_id);

        if (! $funnel instanceof Funnel) {
            return 'Воронка уже удалена.';
        }

        return $this->bulkDeleteService->deleteFunnel($project, $funnel);
    }
}

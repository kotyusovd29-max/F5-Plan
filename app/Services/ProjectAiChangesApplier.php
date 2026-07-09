<?php

namespace App\Services;

use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\ProjectIntegration;
use App\Models\ProjectSkkField;
use Illuminate\Support\Facades\DB;

class ProjectAiChangesApplier
{
    public function __construct(
        private readonly CanvasLayoutService $canvasLayoutService
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, int>
     */
    public function apply(Project $project, array $changes): array
    {
        $counts = [
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
        ];

        DB::transaction(function () use ($project, $changes, &$counts) {
            if (isset($changes['project']['fields']) && is_array($changes['project']['fields'])) {
                $fields = collect($changes['project']['fields'])
                    ->only(['company_name', 'employees_count', 'client_tasks'])
                    ->filter(fn ($value) => $value !== null && $value !== '')
                    ->all();

                if ($fields !== []) {
                    $project->update($fields);
                    $counts['updated']++;
                }
            }

            if (isset($changes['skk_fields'])) {
                $this->applySkkFieldChanges($project, $changes['skk_fields'], $counts);
            }

            if (isset($changes['integrations'])) {
                $this->applyIntegrationChanges($project, $changes['integrations'], $counts);
            }

            if (isset($changes['funnels'])) {
                $this->applyFunnelChanges($project, $changes['funnels'], $counts);
            }

            if (isset($changes['stages'])) {
                $this->applyStageChanges($project, $changes['stages'], $counts);
            }
        });

        $this->canvasLayoutService->syncBlocks($project->fresh());

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<string, int>  $counts
     */
    private function applySkkFieldChanges(Project $project, array $section, array &$counts): void
    {
        foreach ($section['delete'] ?? [] as $id) {
            $field = $project->skkFields()->find($id);

            if (! $field) {
                continue;
            }

            $this->deleteSkkField($project, $field);
            $counts['deleted']++;
        }

        foreach ($section['update'] ?? [] as $item) {
            $field = $project->skkFields()->find($item['id'] ?? null);

            if (! $field) {
                continue;
            }

            $field->update(collect($item)->only(['name', 'comment'])->filter(fn ($v) => $v !== null)->all());
            $counts['updated']++;
        }

        foreach ($section['create'] ?? [] as $item) {
            $entityType = $item['entity_type'] ?? null;

            if (! in_array($entityType, ProjectSkkField::TYPES, true) || ($item['name'] ?? '') === '') {
                continue;
            }

            $sortOrder = (int) ($project->skkFields()->where('entity_type', $entityType)->max('sort_order') ?? 0) + 1;

            $project->skkFields()->create([
                'entity_type' => $entityType,
                'name' => $item['name'],
                'comment' => $item['comment'] ?? null,
                'sort_order' => $sortOrder,
            ]);

            $counts['created']++;
        }
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<string, int>  $counts
     */
    private function applyIntegrationChanges(Project $project, array $section, array &$counts): void
    {
        foreach ($section['delete'] ?? [] as $id) {
            $integration = $project->integrations()->find($id);

            if (! $integration) {
                continue;
            }

            $this->deleteIntegration($project, $integration);
            $counts['deleted']++;
        }

        foreach ($section['update'] ?? [] as $item) {
            $integration = $project->integrations()->find($item['id'] ?? null);

            if (! $integration) {
                continue;
            }

            $integration->update(collect($item)->only(['name', 'comment'])->filter(fn ($v) => $v !== null)->all());
            $counts['updated']++;
        }

        foreach ($section['create'] ?? [] as $item) {
            if (($item['name'] ?? '') === '') {
                continue;
            }

            $sortOrder = (int) ($project->integrations()->max('sort_order') ?? 0) + 1;

            $project->integrations()->create([
                'name' => $item['name'],
                'comment' => $item['comment'] ?? null,
                'sort_order' => $sortOrder,
            ]);

            $counts['created']++;
        }
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<string, int>  $counts
     */
    private function applyFunnelChanges(Project $project, array $section, array &$counts): void
    {
        foreach ($section['delete'] ?? [] as $id) {
            $funnel = $project->funnels()->find($id);

            if (! $funnel) {
                continue;
            }

            $this->deleteFunnel($project, $funnel);
            $counts['deleted']++;
        }

        foreach ($section['update'] ?? [] as $item) {
            $funnel = $project->funnels()->find($item['id'] ?? null);

            if (! $funnel) {
                continue;
            }

            $funnel->update(collect($item)->only(['name', 'description'])->filter(fn ($v) => $v !== null)->all());
            $counts['updated']++;
        }

        foreach ($section['create'] ?? [] as $item) {
            if (($item['name'] ?? '') === '') {
                continue;
            }

            $sortOrder = (int) ($project->funnels()->max('sort_order') ?? 0) + 1;

            $funnel = $project->funnels()->create([
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'sort_order' => $sortOrder,
            ]);

            $counts['created']++;

            foreach ($item['stages'] ?? [] as $index => $stageData) {
                if (($stageData['name'] ?? '') === '') {
                    continue;
                }

                $funnel->stages()->create([
                    'name' => $stageData['name'],
                    'action' => $stageData['action'] ?: null,
                    'comment' => $stageData['comment'] ?: null,
                    'sort_order' => $index + 1,
                    'canvas_pos_x' => 80 + ($index * 300),
                    'canvas_pos_y' => 200,
                ]);

                $counts['created']++;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<string, int>  $counts
     */
    private function applyStageChanges(Project $project, array $section, array &$counts): void
    {
        foreach ($section['delete'] ?? [] as $id) {
            $stage = $this->findStageForProject($project, (int) $id);

            if (! $stage) {
                continue;
            }

            $this->deleteStage($stage);
            $counts['deleted']++;
        }

        foreach ($section['update'] ?? [] as $item) {
            $stage = $this->findStageForProject($project, (int) ($item['id'] ?? 0));

            if (! $stage) {
                continue;
            }

            $stage->update(collect($item)->only(['name', 'action', 'comment'])->filter(fn ($v) => $v !== null)->all());
            $counts['updated']++;
        }

        foreach ($section['create'] ?? [] as $item) {
            $funnel = $project->funnels()->find($item['funnel_id'] ?? null);

            if (! $funnel || ($item['name'] ?? '') === '') {
                continue;
            }

            $sortOrder = (int) ($funnel->stages()->max('sort_order') ?? 0) + 1;
            $existingCount = $funnel->stages()->count();

            $funnel->stages()->create([
                'name' => $item['name'],
                'action' => $item['action'] ?? null,
                'comment' => $item['comment'] ?? null,
                'sort_order' => $sortOrder,
                'canvas_pos_x' => 80 + ($existingCount * 300),
                'canvas_pos_y' => 200,
            ]);

            $counts['created']++;
        }
    }

    private function deleteSkkField(Project $project, ProjectSkkField $field): void
    {
        $entityType = $field->entity_type;
        $deletedOrder = $field->sort_order;
        $field->delete();

        $project->skkFields()
            ->where('entity_type', $entityType)
            ->where('sort_order', '>', $deletedOrder)
            ->decrement('sort_order');
    }

    private function deleteIntegration(Project $project, ProjectIntegration $integration): void
    {
        $deletedOrder = $integration->sort_order;
        $integration->delete();

        $project->integrations()
            ->where('sort_order', '>', $deletedOrder)
            ->decrement('sort_order');
    }

    private function deleteFunnel(Project $project, Funnel $funnel): void
    {
        $deletedOrder = $funnel->sort_order;
        $funnel->delete();

        $project->funnels()
            ->where('sort_order', '>', $deletedOrder)
            ->decrement('sort_order');
    }

    private function deleteStage(FunnelStage $stage): void
    {
        $funnel = $stage->funnel;
        $deletedOrder = $stage->sort_order;
        $stage->delete();

        $funnel->stages()
            ->where('sort_order', '>', $deletedOrder)
            ->decrement('sort_order');
    }

    private function findStageForProject(Project $project, int $stageId): ?FunnelStage
    {
        return FunnelStage::query()
            ->where('id', $stageId)
            ->whereHas('funnel', fn ($query) => $query->where('project_id', $project->id))
            ->first();
    }
}

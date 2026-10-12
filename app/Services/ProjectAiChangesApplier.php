<?php

namespace App\Services;

use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\ProjectIntegration;
use App\Models\ProjectSkkField;
use App\Support\F5WidgetCatalog;
use App\Support\FunnelStageOutcomes;
use App\Support\FunnelStageWidgets;
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
                    ->only(['company_name', 'website', 'business_sphere', 'current_services', 'employees_count', 'client_tasks'])
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

            if (isset($changes['work_stages'])) {
                $this->applyWorkStageChanges($project, $changes['work_stages'], $counts);
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

            $field->update($this->normalizeSkkAttributes(
                $project,
                collect($item)->only([
                    'name',
                    'comment',
                    'value_type',
                    'is_required',
                    'show_in_card',
                    'show_in_list',
                    'show_in_filter',
                    'options',
                    'funnel_stage_id',
                ])->filter(fn ($v) => $v !== null)->all()
            ));
            $counts['updated']++;
        }

        foreach ($section['create'] ?? [] as $item) {
            $entityType = $item['entity_type'] ?? null;

            if (! in_array($entityType, ProjectSkkField::TYPES, true) || ($item['name'] ?? '') === '') {
                continue;
            }

            $sortOrder = (int) ($project->skkFields()->where('entity_type', $entityType)->max('sort_order') ?? 0) + 1;

            $project->skkFields()->create([
                ...$this->normalizeSkkAttributes($project, $item),
                'entity_type' => $entityType,
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

            $resolved = F5WidgetCatalog::normalizeIntegrationInput([
                'widget_slug' => $item['widget_slug'] ?? $integration->widget_slug,
                'name' => $item['name'] ?? $integration->name,
                'comment' => array_key_exists('comment', $item) ? $item['comment'] : $integration->comment,
            ]);

            $integration->update($resolved);
            $counts['updated']++;
        }

        foreach ($section['create'] ?? [] as $item) {
            $resolved = F5WidgetCatalog::normalizeIntegrationInput($item);

            if ($resolved['name'] === '') {
                continue;
            }

            $sortOrder = (int) ($project->integrations()->max('sort_order') ?? 0) + 1;

            $project->integrations()->create([
                ...$resolved,
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

            $this->createStagesForFunnel($funnel, $item['stages'] ?? [], $counts);
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

            $payload = $this->stageAttributePayload($item);

            if (array_key_exists('outcomes', $item)) {
                $nameToId = $stage->funnel->stages()
                    ->get(['id', 'name'])
                    ->mapWithKeys(fn (FunnelStage $row) => [mb_strtolower(trim((string) $row->name)) => (int) $row->id])
                    ->all();

                $widgets = $payload['widgets']
                    ?? FunnelStageWidgets::normalize($item['widgets'] ?? (is_array($stage->widgets) ? $stage->widgets : []));

                $payload['outcomes'] = FunnelStageOutcomes::normalize(
                    $this->resolveStageOutcomeTargets(
                        is_array($item['outcomes']) ? $item['outcomes'] : [],
                        $nameToId
                    ),
                    $widgets
                );
            }

            if ($payload === []) {
                continue;
            }

            $stage->update($payload);
            $counts['updated']++;
        }

        foreach ($section['create'] ?? [] as $item) {
            $funnel = $project->funnels()->find($item['funnel_id'] ?? null);

            if (! $funnel || ($item['name'] ?? '') === '') {
                continue;
            }

            $this->createStagesForFunnel($funnel, [$item], $counts);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $stages
     * @param  array<string, int>  $counts
     */
    private function createStagesForFunnel(Funnel $funnel, array $stages, array &$counts): void
    {
        $pending = [];
        $sortOrderToId = [];
        $nameToId = [];
        $baseSort = (int) ($funnel->stages()->max('sort_order') ?? 0);
        $existingCount = $funnel->stages()->count();

        foreach ($stages as $index => $stageData) {
            if (($stageData['name'] ?? '') === '') {
                continue;
            }

            $sortOrder = $baseSort + $index + 1;
            $payload = $this->stageAttributePayload($stageData, forCreate: true);
            $payload['name'] = $stageData['name'];
            $payload['sort_order'] = $sortOrder;
            $payload['canvas_pos_x'] = 80 + (($existingCount + $index) * 300);
            $payload['canvas_pos_y'] = 200;
            $payload['outcomes'] = [];

            $stage = $funnel->stages()->create($payload);

            $pending[] = [$stage, $stageData];
            $sortOrderToId[$sortOrder] = $stage->id;
            $sortOrderToId[$index + 1] = $stage->id;
            $nameToId[mb_strtolower(trim((string) $stage->name))] = $stage->id;
            $counts['created']++;
        }

        foreach ($pending as [$stage, $stageData]) {
            if (! array_key_exists('outcomes', $stageData)) {
                continue;
            }

            $outcomes = $this->resolveStageOutcomeTargets(
                is_array($stageData['outcomes']) ? $stageData['outcomes'] : [],
                $nameToId
            );
            $widgets = FunnelStageWidgets::normalize(
                $stageData['widgets'] ?? (is_array($stage->widgets) ? $stage->widgets : [])
            );

            $stage->update([
                'outcomes' => FunnelStageOutcomes::toProjectIds(
                    FunnelStageOutcomes::normalize($outcomes, $widgets),
                    $sortOrderToId
                ),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function stageAttributePayload(array $item, bool $forCreate = false): array
    {
        $payload = [];

        foreach (['name', 'action', 'comment', 'stage_type', 'deadline'] as $field) {
            if (! array_key_exists($field, $item)) {
                continue;
            }

            $value = $item[$field];

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($forCreate && in_array($field, ['action', 'comment', 'stage_type', 'deadline'], true)) {
                $payload[$field] = $value !== '' && $value !== null ? $value : null;

                continue;
            }

            if ($value !== null) {
                $payload[$field] = $value === '' ? null : $value;
            }
        }

        if (array_key_exists('kind', $item)) {
            $kind = trim((string) $item['kind']);
            $payload['kind'] = in_array($kind, FunnelStageOutcomes::KINDS, true) ? $kind : 'normal';
        } elseif ($forCreate) {
            $payload['kind'] = 'normal';
        }

        if (array_key_exists('widgets', $item)) {
            $payload['widgets'] = FunnelStageWidgets::normalize($item['widgets']);
        }

        if (array_key_exists('outcomes', $item) && ! $forCreate) {
            $widgets = $payload['widgets']
                ?? FunnelStageWidgets::normalize($item['widgets'] ?? []);
            $payload['outcomes'] = FunnelStageOutcomes::normalize($item['outcomes'], $widgets);
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $outcomes
     * @param  array<string, int>  $nameToId
     * @return list<array<string, mixed>>
     */
    private function resolveStageOutcomeTargets(array $outcomes, array $nameToId): array
    {
        return array_map(function (array $row) use ($nameToId) {
            $name = trim((string) ($row['target_stage_name'] ?? ''));

            if ($name !== '') {
                $mapped = $nameToId[mb_strtolower($name)] ?? null;

                if ($mapped !== null) {
                    $row['target_stage_id'] = $mapped;
                }
            }

            unset($row['target_stage_name']);

            return $row;
        }, $outcomes);
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<string, int>  $counts
     */
    private function applyWorkStageChanges(Project $project, array $section, array &$counts): void
    {
        foreach ($section['delete'] ?? [] as $id) {
            $stage = $project->workStages()->find($id);

            if (! $stage) {
                continue;
            }

            $deletedOrder = $stage->sort_order;
            $stage->delete();

            $project->workStages()
                ->where('sort_order', '>', $deletedOrder)
                ->decrement('sort_order');

            $counts['deleted']++;
        }

        foreach ($section['update'] ?? [] as $item) {
            $stage = $project->workStages()->find($item['id'] ?? null);

            if (! $stage) {
                continue;
            }

            $payload = collect($item)
                ->only(['name', 'composition', 'cost', 'duration_days'])
                ->filter(fn ($value, $key) => in_array($key, ['cost', 'composition', 'duration_days'], true) || ($value !== null && $value !== ''))
                ->all();

            if ($payload === []) {
                continue;
            }

            $stage->update($payload);
            $counts['updated']++;
        }

        foreach ($section['create'] ?? [] as $item) {
            $name = trim((string) ($item['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $sortOrder = (int) ($project->workStages()->max('sort_order') ?? 0) + 1;

            $project->workStages()->create([
                'name' => $name,
                'composition' => $item['composition'] ?? null,
                'cost' => $item['cost'] ?? null,
                'duration_days' => $item['duration_days'] ?? null,
                'sort_order' => $sortOrder,
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

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeSkkAttributes(Project $project, array $attributes): array
    {
        $normalized = ProjectSkkField::normalizeAttributes($attributes);

        if (! empty($normalized['funnel_stage_id'])
            && ! $this->findStageForProject($project, (int) $normalized['funnel_stage_id'])) {
            $normalized['funnel_stage_id'] = null;
        }

        return $normalized;
    }
}

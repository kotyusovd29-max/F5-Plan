<?php

namespace App\Services;

use App\Models\Funnel;
use App\Models\Project;
use App\Models\ProjectAiApplySnapshot;
use App\Models\ProjectAiMessage;
use App\Models\ProjectIntegration;
use App\Models\ProjectSkkField;
use App\Models\ProjectWorkStage;
use App\Support\F5WidgetCatalog;
use App\Support\FunnelBoardWidgets;
use App\Support\FunnelStageOutcomes;
use App\Support\FunnelStageWidgets;
use Illuminate\Support\Facades\DB;

class ProjectAiSnapshotService
{
    public function __construct(
        private readonly CanvasLayoutService $canvasLayoutService
    ) {}

    public function capture(Project $project, ProjectAiMessage $message): ProjectAiApplySnapshot
    {
        $project->load([
            'funnels.stages',
            'skkFields',
            'integrations',
            'workStages',
        ]);

        $payload = [
            'project' => [
                'company_name' => $project->company_name,
                'website' => $project->website,
                'business_sphere' => $project->business_sphere,
                'current_services' => $project->current_services,
                'employees_count' => $project->employees_count,
                'client_tasks' => $project->client_tasks,
            ],
            'funnels' => $project->funnels->map(function (Funnel $funnel) {
                return [
                    'name' => $funnel->name,
                    'description' => $funnel->description,
                    'sort_order' => $funnel->sort_order,
                    'board_widgets' => is_array($funnel->board_widgets) ? $funnel->board_widgets : [],
                    'stages' => $funnel->stages->map(fn ($stage) => [
                        'old_id' => $stage->id,
                        'name' => $stage->name,
                        'action' => $stage->action,
                        'comment' => $stage->comment,
                        'stage_type' => $stage->stage_type,
                        'deadline' => $stage->deadline,
                        'kind' => $stage->kind ?: 'normal',
                        'outcomes' => is_array($stage->outcomes) ? $stage->outcomes : [],
                        'widgets' => is_array($stage->widgets) ? $stage->widgets : [],
                        'color' => $stage->color,
                        'sort_order' => $stage->sort_order,
                        'canvas_pos_x' => $stage->canvas_pos_x,
                        'canvas_pos_y' => $stage->canvas_pos_y,
                    ])->values()->all(),
                ];
            })->values()->all(),
            'skk_fields' => $project->skkFields->map(fn (ProjectSkkField $field) => [
                'entity_type' => $field->entity_type,
                'name' => $field->name,
                'value_type' => $field->value_type,
                'is_required' => (bool) $field->is_required,
                'show_in_card' => (bool) $field->show_in_card,
                'show_in_list' => (bool) $field->show_in_list,
                'show_in_filter' => (bool) $field->show_in_filter,
                'options' => $field->options ?? [],
                'funnel_stage_old_id' => $field->funnel_stage_id,
                'comment' => $field->comment,
                'sort_order' => $field->sort_order,
            ])->values()->all(),
            'integrations' => $project->integrations->map(fn (ProjectIntegration $item) => [
                'widget_slug' => $item->widget_slug,
                'widget_url' => $item->widget_url,
                'name' => $item->name,
                'comment' => $item->comment,
                'billing_quantity' => $item->billing_quantity,
                'payment_type' => $item->payment_type,
                'cost' => $item->cost,
                'sort_order' => $item->sort_order,
            ])->values()->all(),
            'work_stages' => $project->workStages->map(fn (ProjectWorkStage $stage) => [
                'name' => $stage->name,
                'composition' => $stage->composition,
                'cost' => $stage->cost,
                'duration_days' => $stage->duration_days,
                'sort_order' => $stage->sort_order,
            ])->values()->all(),
        ];

        return ProjectAiApplySnapshot::query()->updateOrCreate(
            ['project_ai_message_id' => $message->id],
            [
                'project_id' => $project->id,
                'payload' => $payload,
                'restored_at' => null,
            ]
        );
    }

    public function restore(ProjectAiApplySnapshot $snapshot): void
    {
        if (! $snapshot->canRestore()) {
            throw new \RuntimeException('Снимок уже восстановлен или пуст.');
        }

        $project = Project::query()->findOrFail($snapshot->project_id);
        $payload = $snapshot->payload;

        DB::transaction(function () use ($project, $payload, $snapshot) {
            $project->skkFields()->delete();
            $project->integrations()->delete();
            $project->workStages()->delete();
            $project->funnels()->with('stages')->get()->each(function (Funnel $funnel) {
                $funnel->stages()->delete();
                $funnel->delete();
            });

            $fields = collect($payload['project'] ?? [])
                ->only(['company_name', 'website', 'business_sphere', 'current_services', 'employees_count', 'client_tasks'])
                ->all();

            if ($fields !== []) {
                $project->update($fields);
            }

            $stageIdMap = [];

            foreach ($payload['funnels'] ?? [] as $funnelData) {
                $funnel = $project->funnels()->create([
                    'name' => $funnelData['name'] ?? 'Воронка',
                    'description' => $funnelData['description'] ?? null,
                    'sort_order' => $funnelData['sort_order'] ?? 0,
                    'board_widgets' => [],
                ]);

                $localStageMap = [];

                foreach ($funnelData['stages'] ?? [] as $stageData) {
                    $stage = $funnel->stages()->create([
                        'name' => $stageData['name'] ?? 'Этап',
                        'action' => $stageData['action'] ?? null,
                        'comment' => $stageData['comment'] ?? null,
                        'stage_type' => $stageData['stage_type'] ?? null,
                        'deadline' => $stageData['deadline'] ?? null,
                        'kind' => $stageData['kind'] ?? 'normal',
                        'outcomes' => [],
                        'widgets' => FunnelStageWidgets::normalize($stageData['widgets'] ?? []),
                        'color' => $stageData['color'] ?? null,
                        'sort_order' => $stageData['sort_order'] ?? 0,
                        'canvas_pos_x' => $stageData['canvas_pos_x'] ?? null,
                        'canvas_pos_y' => $stageData['canvas_pos_y'] ?? null,
                    ]);

                    $oldId = (int) ($stageData['old_id'] ?? 0);
                    if ($oldId > 0) {
                        $stageIdMap[$oldId] = (int) $stage->id;
                        $localStageMap[$oldId] = (int) $stage->id;
                    }
                }

                foreach ($funnelData['stages'] ?? [] as $stageData) {
                    $oldId = (int) ($stageData['old_id'] ?? 0);
                    $newId = $localStageMap[$oldId] ?? null;
                    if ($newId === null) {
                        continue;
                    }

                    $stage = $funnel->stages()->find($newId);
                    if (! $stage) {
                        continue;
                    }

                    $outcomes = FunnelStageOutcomes::normalize(
                        is_array($stageData['outcomes'] ?? null) ? $stageData['outcomes'] : [],
                        FunnelStageWidgets::normalize($stageData['widgets'] ?? [])
                    );

                    foreach ($outcomes as &$outcome) {
                        $targetId = $outcome['target_stage_id'] ?? null;
                        if ($targetId !== null && isset($stageIdMap[(int) $targetId])) {
                            $outcome['target_stage_id'] = $stageIdMap[(int) $targetId];
                        }
                    }
                    unset($outcome);

                    $stage->update(['outcomes' => $outcomes]);
                }

                $funnel->update([
                    'board_widgets' => FunnelBoardWidgets::normalize($funnelData['board_widgets'] ?? []),
                ]);
            }

            foreach ($payload['skk_fields'] ?? [] as $fieldData) {
                $oldStageId = $fieldData['funnel_stage_old_id'] ?? null;
                $project->skkFields()->create([
                    'entity_type' => $fieldData['entity_type'] ?? 'deals',
                    'name' => $fieldData['name'] ?? 'Поле',
                    'value_type' => $fieldData['value_type'] ?? 'text',
                    'is_required' => (bool) ($fieldData['is_required'] ?? false),
                    'show_in_card' => (bool) ($fieldData['show_in_card'] ?? true),
                    'show_in_list' => (bool) ($fieldData['show_in_list'] ?? false),
                    'show_in_filter' => (bool) ($fieldData['show_in_filter'] ?? false),
                    'options' => $fieldData['options'] ?? [],
                    'funnel_stage_id' => $oldStageId !== null
                        ? ($stageIdMap[(int) $oldStageId] ?? null)
                        : null,
                    'comment' => $fieldData['comment'] ?? null,
                    'sort_order' => $fieldData['sort_order'] ?? 0,
                ]);
            }

            foreach ($payload['integrations'] ?? [] as $integrationData) {
                $resolved = F5WidgetCatalog::normalizeIntegrationInput([
                    'widget_slug' => $integrationData['widget_slug'] ?? null,
                    'name' => $integrationData['name'] ?? null,
                    'comment' => $integrationData['comment'] ?? null,
                    'billing_quantity' => $integrationData['billing_quantity'] ?? null,
                ]);

                $project->integrations()->create([
                    'widget_slug' => $resolved['widget_slug'],
                    'widget_url' => $resolved['widget_url'] ?? ($integrationData['widget_url'] ?? null),
                    'name' => $resolved['name'] !== '' ? $resolved['name'] : ($integrationData['name'] ?? 'Интеграция'),
                    'comment' => $resolved['comment'],
                    'billing_quantity' => $resolved['billing_quantity'],
                    'payment_type' => $integrationData['payment_type'] ?? null,
                    'cost' => $integrationData['cost'] ?? null,
                    'sort_order' => $integrationData['sort_order'] ?? 0,
                ]);
            }

            foreach ($payload['work_stages'] ?? [] as $workStageData) {
                $project->workStages()->create([
                    'name' => $workStageData['name'] ?? 'Этап',
                    'composition' => $workStageData['composition'] ?? null,
                    'cost' => $workStageData['cost'] ?? null,
                    'duration_days' => $workStageData['duration_days'] ?? null,
                    'sort_order' => $workStageData['sort_order'] ?? 0,
                ]);
            }

            $snapshot->update(['restored_at' => now()]);
        });

        $this->canvasLayoutService->syncBlocks($project->fresh());
    }
}

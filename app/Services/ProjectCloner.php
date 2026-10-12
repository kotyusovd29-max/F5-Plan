<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProjectCloner
{
    public function clone(Project $source, User $owner): Project
    {
        $source->load([
            'funnels.stages',
            'skkFields',
            'integrations',
            'workStages',
        ]);

        return DB::transaction(function () use ($source, $owner) {
            $clone = $owner->projects()->create([
                'company_name' => $this->cloneCompanyName($source->company_name),
                'website' => null,
                'business_sphere' => $source->business_sphere,
                'current_services' => null,
                'employees_count' => $source->employees_count,
                'client_tasks' => null,
                'amocrm_deal_url' => null,
                'amocrm_license_tariff' => $source->amocrm_license_tariff,
                'status' => Project::STATUS_DRAFT,
                'status_changed_at' => now(),
            ]);

            $stageIdMap = $this->cloneFunnels($source, $clone);
            $this->cloneSkkFields($source, $clone, $stageIdMap);
            $this->cloneIntegrations($source, $clone);
            $this->cloneWorkStages($source, $clone);

            return $clone->fresh([
                'funnels.stages',
                'skkFields',
                'integrations',
                'workStages',
            ]);
        });
    }

    private function cloneCompanyName(string $name): string
    {
        $base = trim($name);

        if ($base === '') {
            $base = 'Проект';
        }

        $candidate = 'Копия: '.$base;

        if (mb_strlen($candidate) <= 255) {
            return $candidate;
        }

        return mb_substr($candidate, 0, 255);
    }

    /**
     * @return array<int, int> old stage id => new stage id
     */
    private function cloneFunnels(Project $source, Project $clone): array
    {
        $stageIdMap = [];

        foreach ($source->funnels as $funnel) {
            $widgetIdMap = [];
            $sourceBoardWidgets = is_array($funnel->board_widgets) ? $funnel->board_widgets : [];

            foreach ($sourceBoardWidgets as $boardWidget) {
                if (! is_array($boardWidget)) {
                    continue;
                }

                $oldId = trim((string) ($boardWidget['id'] ?? ''));

                if ($oldId !== '') {
                    $widgetIdMap[$oldId] = (string) \Illuminate\Support\Str::uuid();
                }
            }

            $newFunnel = $clone->funnels()->create([
                'name' => $funnel->name,
                'description' => $funnel->description,
                'sort_order' => $funnel->sort_order,
                'amocrm_id' => null,
                'board_widgets' => [],
            ]);

            $pendingOutcomes = [];
            $funnelStageIdMap = [];

            foreach ($funnel->stages as $stage) {
                $newStage = $newFunnel->stages()->create([
                    'name' => $stage->name,
                    'action' => $stage->action,
                    'comment' => $stage->comment,
                    'stage_type' => $stage->stage_type,
                    'deadline' => $stage->deadline,
                    'kind' => $stage->kind ?: 'normal',
                    'outcomes' => [],
                    'widgets' => is_array($stage->widgets) ? $stage->widgets : [],
                    'color' => $stage->color,
                    'sort_order' => $stage->sort_order,
                    'amocrm_id' => null,
                ]);

                $stageIdMap[(int) $stage->id] = (int) $newStage->id;
                $funnelStageIdMap[(int) $stage->id] = (int) $newStage->id;
                $pendingOutcomes[] = [$newStage, $stage->outcomes];
            }

            $newFunnel->update([
                'board_widgets' => \App\Support\FunnelBoardWidgets::remap(
                    $sourceBoardWidgets,
                    $widgetIdMap,
                    $funnelStageIdMap
                ),
            ]);

            foreach ($pendingOutcomes as [$newStage, $outcomes]) {
                $newStage->update([
                    'outcomes' => \App\Support\FunnelStageOutcomes::remapIds(
                        $outcomes,
                        $stageIdMap,
                        $widgetIdMap
                    ),
                ]);
            }
        }

        return $stageIdMap;
    }

    /**
     * @param  array<int, int>  $stageIdMap
     */
    private function cloneSkkFields(Project $source, Project $clone, array $stageIdMap): void
    {
        foreach ($source->skkFields as $field) {
            $oldStageId = $field->funnel_stage_id ? (int) $field->funnel_stage_id : null;

            $clone->skkFields()->create([
                'entity_type' => $field->entity_type,
                'name' => $field->name,
                'value_type' => $field->value_type,
                'is_required' => $field->is_required,
                'show_in_card' => $field->show_in_card,
                'show_in_list' => $field->show_in_list,
                'show_in_filter' => $field->show_in_filter,
                'options' => $field->options,
                'funnel_stage_id' => $oldStageId !== null
                    ? ($stageIdMap[$oldStageId] ?? null)
                    : null,
                'comment' => $field->comment,
                'sort_order' => $field->sort_order,
                'amocrm_id' => null,
            ]);
        }
    }

    private function cloneIntegrations(Project $source, Project $clone): void
    {
        foreach ($source->integrations as $integration) {
            $clone->integrations()->create([
                'name' => $integration->name,
                'widget_slug' => $integration->widget_slug,
                'widget_url' => $integration->widget_url,
                'comment' => $integration->comment,
                'billing_quantity' => $integration->billing_quantity,
                'payment_type' => $integration->payment_type,
                'cost' => $integration->cost,
                'sort_order' => $integration->sort_order,
            ]);
        }
    }

    private function cloneWorkStages(Project $source, Project $clone): void
    {
        foreach ($source->workStages as $stage) {
            $clone->workStages()->create([
                'name' => $stage->name,
                'composition' => $stage->composition,
                'cost' => $stage->cost,
                'duration_days' => $stage->duration_days,
                'sort_order' => $stage->sort_order,
            ]);
        }
    }
}

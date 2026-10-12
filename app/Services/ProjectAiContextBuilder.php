<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectSkkField;
use App\Support\F5WidgetCatalog;

class ProjectAiContextBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Project $project): array
    {
        $project->loadMissing([
            'skkFields.funnelStage.funnel',
            'integrations',
            'funnels.stages',
            'workStages',
        ]);

        $skkFields = [];

        foreach (ProjectSkkField::TYPES as $type) {
            $skkFields[$type] = $project->skkFields
                ->where('entity_type', $type)
                ->values()
                ->map(fn (ProjectSkkField $field) => [
                    'id' => $field->id,
                    'name' => $field->name,
                    'value_type' => $field->value_type,
                    'is_required' => $field->is_required,
                    'show_in_card' => $field->show_in_card,
                    'show_in_list' => $field->show_in_list,
                    'show_in_filter' => $field->show_in_filter,
                    'options' => $field->options,
                    'funnel_stage_id' => $field->funnel_stage_id,
                    'comment' => $field->comment,
                ])
                ->all();
        }

        return [
            'project' => $this->projectSummary($project),
            'skk_fields' => $skkFields,
            'integrations' => $project->integrations->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'widget_slug' => $item->widget_slug,
                'widget_url' => $item->widget_url,
                'comment' => $item->comment,
            ])->values()->all(),
            'funnels' => $project->funnels->map(fn ($funnel) => [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'description' => $funnel->description,
                'board_widgets' => is_array($funnel->board_widgets) ? $funnel->board_widgets : [],
                'stages' => $funnel->stages->map(fn ($stage) => [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'action' => $stage->action,
                    'comment' => $stage->comment,
                    'stage_type' => $stage->stage_type,
                    'deadline' => $stage->deadline,
                    'kind' => $stage->kind ?: 'normal',
                    'outcomes' => is_array($stage->outcomes) ? $stage->outcomes : [],
                    'widgets' => is_array($stage->widgets) ? $stage->widgets : [],
                ])->values()->all(),
            ])->values()->all(),
            'work_stages' => $project->workStages->map(fn ($stage) => [
                'id' => $stage->id,
                'name' => $stage->name,
                'composition' => $stage->composition,
                'cost' => $stage->cost,
                'duration_days' => $stage->duration_days,
            ])->values()->all(),
            'f5_widgets' => F5WidgetCatalog::forAiContext(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectSummary(Project $project): array
    {
        return [
            'company_name' => $project->company_name,
            'website' => $project->website,
            'business_sphere' => $project->business_sphere,
            'current_services' => $project->current_services,
            'employees_count' => $project->employees_count,
            'client_tasks' => $project->client_tasks,
        ];
    }
}

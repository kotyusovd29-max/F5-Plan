<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectSkkField;

class ProjectAiContextBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Project $project): array
    {
        $project->loadMissing([
            'skkFields',
            'integrations',
            'funnels.stages',
        ]);

        $skkFields = [];

        foreach (ProjectSkkField::TYPES as $type) {
            $skkFields[$type] = $project->skkFields
                ->where('entity_type', $type)
                ->values()
                ->map(fn (ProjectSkkField $field) => [
                    'id' => $field->id,
                    'name' => $field->name,
                    'comment' => $field->comment,
                ])
                ->all();
        }

        return [
            'project' => [
                'company_name' => $project->company_name,
                'employees_count' => $project->employees_count,
                'client_tasks' => $project->client_tasks,
            ],
            'skk_fields' => $skkFields,
            'integrations' => $project->integrations->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'comment' => $item->comment,
            ])->values()->all(),
            'funnels' => $project->funnels->map(fn ($funnel) => [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'description' => $funnel->description,
                'stages' => $funnel->stages->map(fn ($stage) => [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'action' => $stage->action,
                    'comment' => $stage->comment,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}

<?php

namespace App\Support;

use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\ProjectSkkField;

class SkkFieldPresenter
{
    /**
     * @return list<array{id: int, label: string}>
     */
    public static function funnelStageOptions(Project $project): array
    {
        $project->loadMissing('funnels.stages');

        $options = [];

        foreach ($project->funnels as $funnel) {
            foreach ($funnel->stages as $stage) {
                $options[] = [
                    'id' => $stage->id,
                    'label' => $funnel->name.' → '.$stage->name,
                ];
            }
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    public static function forFrontend(ProjectSkkField $field, Project $project): array
    {
        $field->loadMissing('funnelStage.funnel');

        $stageLabel = null;

        if ($field->funnelStage) {
            $funnelName = $field->funnelStage->funnel?->name ?? 'Воронка';
            $stageLabel = $funnelName.' → '.$field->funnelStage->name;
        }

        return [
            'id' => $field->id,
            'name' => $field->name,
            'value_type' => $field->value_type,
            'value_type_label' => ProjectSkkField::VALUE_TYPE_LABELS[$field->value_type] ?? $field->value_type,
            'is_required' => (bool) $field->is_required,
            'show_in_card' => (bool) $field->show_in_card,
            'show_in_list' => (bool) $field->show_in_list,
            'show_in_filter' => (bool) $field->show_in_filter,
            'options' => $field->options ?? [],
            'funnel_stage_id' => $field->funnel_stage_id,
            'funnel_stage_label' => $stageLabel,
            'comment' => $field->comment,
            'summary' => self::summary($field, $stageLabel),
            'update_url' => route('projects.skk-fields.update', [$project, $field]),
            'destroy_url' => route('projects.skk-fields.destroy', [$project, $field]),
        ];
    }

    public static function summary(ProjectSkkField $field, ?string $stageLabel = null): string
    {
        $parts = [ProjectSkkField::VALUE_TYPE_LABELS[$field->value_type] ?? $field->value_type];

        if ($field->is_required) {
            $parts[] = 'обяз.';
        }

        $showIn = [];

        if ($field->show_in_card) {
            $showIn[] = 'карточка';
        }

        if ($field->show_in_list) {
            $showIn[] = 'список';
        }

        if ($field->show_in_filter) {
            $showIn[] = 'фильтр';
        }

        if ($showIn !== []) {
            $parts[] = implode(', ', $showIn);
        }

        if ($stageLabel) {
            $parts[] = $stageLabel;
        }

        return implode(' · ', $parts);
    }

    public static function stageLabelForProject(?FunnelStage $stage, Project $project): ?string
    {
        if (! $stage) {
            return null;
        }

        $project->loadMissing('funnels.stages');
        $funnel = $project->funnels->firstWhere('id', $stage->funnel_id);

        return ($funnel?->name ?? 'Воронка').' → '.$stage->name;
    }
}

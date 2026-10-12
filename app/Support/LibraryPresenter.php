<?php

namespace App\Support;

use App\Models\LibraryFunnel;
use App\Models\LibrarySkkField;
use App\Models\Project;
use App\Models\ProjectSkkField;

class LibraryPresenter
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function funnelsForApply(?Project $project = null): array
    {
        return LibraryFunnel::query()
            ->with('stages')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (LibraryFunnel $funnel) => [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'description' => $funnel->description,
                'stages_count' => $funnel->stages->count(),
                'stages' => $funnel->stages->map(fn ($stage) => [
                    'name' => $stage->name,
                    'action' => $stage->action,
                    'stage_type' => $stage->stage_type,
                    'deadline' => $stage->deadline,
                    'kind' => $stage->kind ?: 'normal',
                    'color' => $stage->color,
                ])->values()->all(),
                'apply_url' => $project
                    ? route('projects.library.funnels.apply', [$project, $funnel])
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function skkFieldsForApply(?Project $project = null): array
    {
        return LibrarySkkField::query()
            ->orderBy('entity_type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (LibrarySkkField $field) => [
                'id' => $field->id,
                'entity_type' => $field->entity_type,
                'entity_label' => ProjectSkkField::TYPE_LABELS[$field->entity_type] ?? $field->entity_type,
                'name' => $field->name,
                'value_type' => $field->value_type,
                'value_type_label' => ProjectSkkField::VALUE_TYPE_LABELS[$field->value_type] ?? $field->value_type,
                'stage_name' => $field->stage_name,
                'comment' => $field->comment,
                'apply_url' => $project
                    ? route('projects.library.skk-fields.apply', [$project, $field])
                    : null,
            ])
            ->values()
            ->all();
    }
}

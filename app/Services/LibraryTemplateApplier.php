<?php

namespace App\Services;

use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\LibraryFunnel;
use App\Models\LibrarySkkField;
use App\Models\Project;
use App\Models\ProjectSkkField;
use App\Support\FunnelStageColors;
use App\Support\FunnelStageOutcomes;
use Illuminate\Support\Facades\DB;

class LibraryTemplateApplier
{
    public function applyFunnel(Project $project, LibraryFunnel $template): Funnel
    {
        $template->loadMissing('stages');

        return DB::transaction(function () use ($project, $template) {
            $sortOrder = ((int) $project->funnels()->max('sort_order')) + 1;

            $funnel = $project->funnels()->create([
                'name' => $template->name,
                'description' => $template->description,
                'sort_order' => $sortOrder,
            ]);

            $created = [];

            foreach ($template->stages as $index => $stage) {
                $created[] = [
                    'template' => $stage,
                    'stage' => $funnel->stages()->create([
                        'name' => $stage->name,
                        'action' => $stage->action,
                        'comment' => $stage->comment,
                        'stage_type' => $stage->stage_type,
                        'deadline' => $stage->deadline,
                        'kind' => $stage->kind ?: 'normal',
                        'outcomes' => [],
                        'widgets' => is_array($stage->widgets) ? $stage->widgets : [],
                        'color' => $stage->color ?: FunnelStageColors::defaultForIndex($index),
                        'sort_order' => $stage->sort_order ?: ($index + 1),
                    ]),
                ];
            }

            $sortOrderToId = collect($created)
                ->mapWithKeys(fn (array $row) => [(int) $row['stage']->sort_order => (int) $row['stage']->id])
                ->all();

            foreach ($created as $row) {
                $row['stage']->update([
                    'outcomes' => FunnelStageOutcomes::toProjectIds(
                        $row['template']->outcomes,
                        $sortOrderToId
                    ),
                ]);
            }

            return $funnel->load('stages');
        });
    }

    public function applySkkField(Project $project, LibrarySkkField $template): ProjectSkkField
    {
        $attributes = ProjectSkkField::normalizeAttributes([
            'name' => $template->name,
            'value_type' => $template->value_type,
            'is_required' => $template->is_required,
            'show_in_card' => $template->show_in_card,
            'show_in_list' => $template->show_in_list,
            'show_in_filter' => $template->show_in_filter,
            'options' => $template->options,
            'comment' => $template->comment,
            'funnel_stage_id' => $this->resolveStageId($project, $template->stage_name),
        ]);

        $sortOrder = ((int) $project->skkFields()
            ->where('entity_type', $template->entity_type)
            ->max('sort_order')) + 1;

        return $project->skkFields()->create([
            ...$attributes,
            'entity_type' => $template->entity_type,
            'sort_order' => $sortOrder,
        ]);
    }

    private function resolveStageId(Project $project, ?string $stageName): ?int
    {
        if ($stageName === null || trim($stageName) === '') {
            return null;
        }

        $needle = mb_strtolower(trim($stageName));

        $stage = FunnelStage::query()
            ->whereHas('funnel', fn ($query) => $query->where('project_id', $project->id))
            ->get()
            ->first(fn (FunnelStage $item) => mb_strtolower(trim($item->name)) === $needle);

        return $stage?->id;
    }
}

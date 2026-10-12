<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectSkkField;
use App\Models\StarterPackage;
use App\Models\StarterPackageSkkField;
use App\Support\F5WidgetCatalog;
use App\Support\FunnelStageColors;
use App\Support\FunnelStageOutcomes;
use Illuminate\Support\Facades\DB;

class StarterPackageApplier
{
    public function apply(Project $project, StarterPackage $package): Project
    {
        $package->load([
            'funnels.stages',
            'skkFields',
            'integrations',
            'workStages',
        ]);

        return DB::transaction(function () use ($project, $package) {
            if (blank($project->business_sphere) && filled($package->business_sphere)) {
                $project->update([
                    'business_sphere' => $package->business_sphere,
                ]);
            }

            $stageLookup = $this->applyFunnels($project, $package);
            $this->applySkkFields($project, $package, $stageLookup);
            $this->applyIntegrations($project, $package);
            $this->applyWorkStages($project, $package);

            return $project->fresh([
                'funnels.stages',
                'skkFields',
                'integrations',
                'workStages',
            ]);
        });
    }

    /**
     * @return array<string, int> lookup key => funnel_stage_id
     */
    private function applyFunnels(Project $project, StarterPackage $package): array
    {
        $stageLookup = [];
        $funnelSort = (int) ($project->funnels()->max('sort_order') ?? 0);

        foreach ($package->funnels as $templateFunnel) {
            $funnelSort++;

            $funnel = $project->funnels()->create([
                'name' => $templateFunnel->name,
                'description' => $templateFunnel->description,
                'sort_order' => $funnelSort,
                'amocrm_id' => null,
            ]);

            $created = [];

            foreach ($templateFunnel->stages as $index => $templateStage) {
                $stage = $funnel->stages()->create([
                    'name' => $templateStage->name,
                    'action' => $templateStage->action,
                    'comment' => $templateStage->comment,
                    'stage_type' => $templateStage->stage_type,
                    'deadline' => $templateStage->deadline,
                    'kind' => $templateStage->kind ?: 'normal',
                    'outcomes' => [],
                    'widgets' => is_array($templateStage->widgets) ? $templateStage->widgets : [],
                    'color' => $templateStage->color ?: FunnelStageColors::defaultForIndex($index),
                    'sort_order' => $templateStage->sort_order ?: ($index + 1),
                    'amocrm_id' => null,
                ]);

                $created[] = ['template' => $templateStage, 'stage' => $stage];
                $stageLookup[$this->stageKey($templateFunnel->name, $templateStage->name)] = (int) $stage->id;
                $stageLookup[$this->stageKey('', $templateStage->name)] ??= (int) $stage->id;
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
        }

        return $stageLookup;
    }

    /**
     * @param  array<string, int>  $stageLookup
     */
    private function applySkkFields(Project $project, StarterPackage $package, array $stageLookup): void
    {
        $sortByType = [];

        foreach (ProjectSkkField::TYPES as $type) {
            $sortByType[$type] = (int) ($project->skkFields()->where('entity_type', $type)->max('sort_order') ?? 0);
        }

        foreach ($package->skkFields as $field) {
            $entityType = in_array($field->entity_type, ProjectSkkField::TYPES, true)
                ? $field->entity_type
                : ProjectSkkField::TYPE_DEALS;

            $sortByType[$entityType]++;

            $attrs = ProjectSkkField::normalizeAttributes([
                'name' => $field->name,
                'value_type' => $field->value_type,
                'is_required' => $field->is_required,
                'show_in_card' => $field->show_in_card,
                'show_in_list' => $field->show_in_list,
                'show_in_filter' => $field->show_in_filter,
                'options' => $field->options,
                'comment' => $field->comment,
            ]);

            $project->skkFields()->create([
                ...$attrs,
                'entity_type' => $entityType,
                'funnel_stage_id' => $this->resolveStageId($field, $stageLookup),
                'sort_order' => $field->sort_order ?: $sortByType[$entityType],
                'amocrm_id' => null,
            ]);
        }
    }

    private function applyIntegrations(Project $project, StarterPackage $package): void
    {
        $sort = (int) ($project->integrations()->max('sort_order') ?? 0);

        foreach ($package->integrations as $integration) {
            $sort++;

            $resolved = F5WidgetCatalog::resolveForStorage([
                'name' => $integration->name,
                'widget_slug' => $integration->widget_slug,
                'widget_url' => $integration->widget_url,
                'comment' => $integration->comment,
                'billing_quantity' => $integration->billing_quantity,
            ]);

            $project->integrations()->create([
                'name' => $resolved['name'],
                'widget_slug' => $resolved['widget_slug'],
                'widget_url' => $resolved['widget_url'],
                'comment' => $resolved['comment'] ?? $integration->comment,
                'billing_quantity' => $resolved['billing_quantity'] ?? $integration->billing_quantity,
                'sort_order' => $integration->sort_order ?: $sort,
            ]);
        }
    }

    private function applyWorkStages(Project $project, StarterPackage $package): void
    {
        $sort = (int) ($project->workStages()->max('sort_order') ?? 0);

        foreach ($package->workStages as $stage) {
            $sort++;

            $project->workStages()->create([
                'name' => $stage->name,
                'composition' => $stage->composition,
                'cost' => $stage->cost,
                'duration_days' => $stage->duration_days,
                'sort_order' => $stage->sort_order ?: $sort,
            ]);
        }
    }

    /**
     * @param  array<string, int>  $stageLookup
     */
    private function resolveStageId(StarterPackageSkkField $field, array $stageLookup): ?int
    {
        $stageName = trim((string) ($field->stage_name ?? ''));

        if ($stageName === '') {
            return null;
        }

        $funnelName = trim((string) ($field->funnel_name ?? ''));
        $exact = $this->stageKey($funnelName, $stageName);

        if (isset($stageLookup[$exact])) {
            return $stageLookup[$exact];
        }

        return $stageLookup[$this->stageKey('', $stageName)] ?? null;
    }

    private function stageKey(string $funnelName, string $stageName): string
    {
        return mb_strtolower(trim($funnelName)).'|'.mb_strtolower(trim($stageName));
    }
}

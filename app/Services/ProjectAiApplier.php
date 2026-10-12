<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectSkkField;
use App\Support\F5WidgetCatalog;
use Illuminate\Support\Facades\DB;

class ProjectAiApplier
{
    /**
     * @param  array<string, mixed>  $proposal
     * @param  array<int, string>  $sections
     * @return array{skk_fields: int, integrations: int, funnels: int, stages: int}
     */
    public function apply(Project $project, array $proposal, array $sections): array
    {
        $counts = [
            'skk_fields' => 0,
            'integrations' => 0,
            'funnels' => 0,
            'stages' => 0,
        ];

        DB::transaction(function () use ($project, $proposal, $sections, &$counts) {
            if (in_array('skk_fields', $sections, true)) {
                $counts['skk_fields'] = $this->applySkkFields($project, $proposal['skk_fields'] ?? []);
            }

            if (in_array('integrations', $sections, true)) {
                $counts['integrations'] = $this->applyIntegrations($project, $proposal['integrations'] ?? []);
            }

            if (in_array('funnels', $sections, true)) {
                [$funnels, $stages] = $this->applyFunnels($project, $proposal['funnels'] ?? []);
                $counts['funnels'] = $funnels;
                $counts['stages'] = $stages;
            }
        });

        return $counts;
    }

    /**
     * @param  array<string, array<int, array<string, string>>>  $skkFields
     */
    private function applySkkFields(Project $project, array $skkFields): int
    {
        $created = 0;

        foreach (ProjectSkkField::TYPES as $entityType) {
            $sortOrder = (int) ($project->skkFields()
                ->where('entity_type', $entityType)
                ->max('sort_order') ?? 0);

            foreach ($skkFields[$entityType] ?? [] as $field) {
                $sortOrder++;

                $project->skkFields()->create([
                    ...ProjectSkkField::normalizeAttributes($field),
                    'entity_type' => $entityType,
                    'sort_order' => $sortOrder,
                ]);

                $created++;
            }
        }

        return $created;
    }

    /**
     * @param  array<int, array<string, string>>  $integrations
     */
    private function applyIntegrations(Project $project, array $integrations): int
    {
        $created = 0;
        $sortOrder = (int) ($project->integrations()->max('sort_order') ?? 0);

        foreach ($integrations as $integration) {
            $resolved = F5WidgetCatalog::normalizeIntegrationInput($integration);

            if ($resolved['name'] === '') {
                continue;
            }

            $sortOrder++;

            $project->integrations()->create([
                ...$resolved,
                'sort_order' => $sortOrder,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * @param  array<int, array{name: string, description: string, stages: array<int, array<string, string>>}>  $funnels
     * @return array{0: int, 1: int}
     */
    private function applyFunnels(Project $project, array $funnels): array
    {
        $createdFunnels = 0;
        $createdStages = 0;
        $funnelSortOrder = (int) ($project->funnels()->max('sort_order') ?? 0);

        foreach ($funnels as $funnelData) {
            $funnelSortOrder++;

            $funnel = $project->funnels()->create([
                'name' => $funnelData['name'],
                'description' => $funnelData['description'] ?: null,
                'sort_order' => $funnelSortOrder,
            ]);

            $createdFunnels++;
            $stageSortOrder = 0;

            foreach ($funnelData['stages'] ?? [] as $index => $stageData) {
                $stageSortOrder++;

                $funnel->stages()->create([
                    'name' => $stageData['name'],
                    'action' => $stageData['action'] ?: null,
                    'comment' => $stageData['comment'] ?: null,
                    'sort_order' => $stageSortOrder,
                    'canvas_pos_x' => 80 + ($index * 300),
                    'canvas_pos_y' => 200,
                ]);

                $createdStages++;
            }
        }

        return [$createdFunnels, $createdStages];
    }
}

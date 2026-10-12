<?php

namespace App\Services;

use App\Models\ProjectSkkField;
use App\Models\StarterPackage;
use App\Support\F5WidgetCatalog;
use App\Support\FunnelStageColors;
use App\Support\FunnelStageOutcomes;
use Illuminate\Support\Facades\DB;

class StarterPackageWriter
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): StarterPackage
    {
        return DB::transaction(function () use ($payload) {
            $package = StarterPackage::query()->create($this->metaAttributes($payload));
            $this->syncChildren($package, $payload);

            return $package->fresh($this->relations());
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(StarterPackage $package, array $payload): StarterPackage
    {
        return DB::transaction(function () use ($package, $payload) {
            $package->update($this->metaAttributes($payload));
            $package->funnels()->each(function ($funnel): void {
                $funnel->stages()->delete();
                $funnel->delete();
            });
            $package->skkFields()->delete();
            $package->integrations()->delete();
            $package->workStages()->delete();
            $this->syncChildren($package, $payload);

            return $package->fresh($this->relations());
        });
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return ['funnels.stages', 'skkFields', 'integrations', 'workStages'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function metaAttributes(array $payload): array
    {
        return [
            'name' => $payload['name'],
            'business_sphere' => $payload['business_sphere'],
            'description' => $payload['description'] ?? null,
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
            'is_active' => (bool) ($payload['is_active'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncChildren(StarterPackage $package, array $payload): void
    {
        foreach ($payload['funnels'] ?? [] as $funnelIndex => $funnelData) {
            $funnel = $package->funnels()->create([
                'name' => $funnelData['name'],
                'description' => $funnelData['description'] ?? null,
                'sort_order' => $funnelIndex + 1,
            ]);

            foreach ($funnelData['stages'] ?? [] as $stageIndex => $stageData) {
                $funnel->stages()->create([
                    'name' => $stageData['name'],
                    'action' => $stageData['action'] ?? null,
                    'comment' => $stageData['comment'] ?? null,
                    'stage_type' => $stageData['stage_type'] ?? null,
                    'deadline' => $stageData['deadline'] ?? null,
                    'kind' => $stageData['kind'] ?? 'normal',
                    'outcomes' => FunnelStageOutcomes::normalize($stageData['outcomes'] ?? []),
                    'color' => $stageData['color'] ?? FunnelStageColors::defaultForIndex($stageIndex),
                    'sort_order' => $stageIndex + 1,
                ]);
            }
        }

        foreach ($payload['skk_fields'] ?? [] as $index => $fieldData) {
            $normalized = ProjectSkkField::normalizeAttributes($fieldData);
            unset($normalized['funnel_stage_id']);

            $package->skkFields()->create([
                ...$normalized,
                'entity_type' => in_array($fieldData['entity_type'] ?? null, ProjectSkkField::TYPES, true)
                    ? $fieldData['entity_type']
                    : ProjectSkkField::TYPE_DEALS,
                'funnel_name' => filled($fieldData['funnel_name'] ?? null)
                    ? trim((string) $fieldData['funnel_name'])
                    : null,
                'stage_name' => filled($fieldData['stage_name'] ?? null)
                    ? trim((string) $fieldData['stage_name'])
                    : null,
                'sort_order' => $index + 1,
            ]);
        }

        foreach ($payload['integrations'] ?? [] as $index => $integrationData) {
            $resolved = F5WidgetCatalog::resolveForStorage($integrationData);

            $package->integrations()->create([
                'name' => $resolved['name'],
                'widget_slug' => $resolved['widget_slug'],
                'widget_url' => $resolved['widget_url'],
                'comment' => filled($integrationData['comment'] ?? null)
                    ? trim((string) $integrationData['comment'])
                    : null,
                'billing_quantity' => $resolved['billing_quantity'] ?? null,
                'sort_order' => $index + 1,
            ]);
        }

        foreach ($payload['work_stages'] ?? [] as $index => $stageData) {
            $package->workStages()->create([
                'name' => $stageData['name'],
                'composition' => filled($stageData['composition'] ?? null)
                    ? trim((string) $stageData['composition'])
                    : null,
                'cost' => filled($stageData['cost'] ?? null) ? $stageData['cost'] : null,
                'duration_days' => filled($stageData['duration_days'] ?? null)
                    ? (int) $stageData['duration_days']
                    : null,
                'sort_order' => $index + 1,
            ]);
        }
    }
}

<?php

namespace App\Support;

use App\Models\ProjectSkkField;
use App\Models\StarterPackage;

class StarterPackagePresenter
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forCreateWizard(): array
    {
        return StarterPackage::query()
            ->active()
            ->ordered()
            ->withCount(['funnels', 'skkFields', 'integrations', 'workStages'])
            ->with(['funnels.stages'])
            ->get()
            ->map(fn (StarterPackage $package) => [
                'id' => $package->id,
                'name' => $package->name,
                'business_sphere' => $package->business_sphere,
                'description' => $package->description,
                'funnels_count' => $package->funnels_count,
                'skk_fields_count' => $package->skk_fields_count,
                'integrations_count' => $package->integrations_count,
                'work_stages_count' => $package->work_stages_count,
                'stages_preview' => $package->funnels
                    ->flatMap(fn ($funnel) => $funnel->stages->pluck('name'))
                    ->take(6)
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function forAdminForm(StarterPackage $package): array
    {
        $package->loadMissing(['funnels.stages', 'skkFields', 'integrations', 'workStages']);

        return [
            'id' => $package->id,
            'name' => $package->name,
            'business_sphere' => $package->business_sphere,
            'description' => $package->description ?? '',
            'sort_order' => $package->sort_order,
            'is_active' => $package->is_active,
            'funnels' => $package->funnels->map(fn ($funnel) => [
                'name' => $funnel->name,
                'description' => $funnel->description ?? '',
                'stages' => $funnel->stages->map(fn ($stage) => [
                    'name' => $stage->name,
                    'action' => $stage->action ?? '',
                    'comment' => $stage->comment ?? '',
                    'stage_type' => $stage->stage_type ?? '',
                    'deadline' => $stage->deadline ?? '',
                    'kind' => $stage->kind ?: 'normal',
                    'outcomes' => is_array($stage->outcomes) ? $stage->outcomes : [],
                    'color' => $stage->color ?? FunnelStageColors::defaultForIndex(0),
                ])->values()->all(),
            ])->values()->all(),
            'skk_fields' => $package->skkFields->map(fn ($field) => [
                'entity_type' => $field->entity_type,
                'name' => $field->name,
                'value_type' => $field->value_type,
                'is_required' => $field->is_required,
                'show_in_card' => $field->show_in_card,
                'show_in_list' => $field->show_in_list,
                'show_in_filter' => $field->show_in_filter,
                'options_text' => is_array($field->options) ? implode("\n", $field->options) : '',
                'funnel_name' => $field->funnel_name ?? '',
                'stage_name' => $field->stage_name ?? '',
                'comment' => $field->comment ?? '',
            ])->values()->all(),
            'integrations' => $package->integrations->map(fn ($item) => [
                'widget_slug' => $item->widget_slug ?? '',
                'name' => $item->name,
                'comment' => $item->comment ?? '',
                'billing_quantity' => $item->billing_quantity,
            ])->values()->all(),
            'work_stages' => $package->workStages->map(fn ($stage) => [
                'name' => $stage->name,
                'composition' => $stage->composition ?? '',
                'cost' => $stage->cost !== null ? (string) $stage->cost : '',
                'duration_days' => $stage->duration_days !== null ? (string) $stage->duration_days : '',
            ])->values()->all(),
            'update_url' => route('admin.starter-packages.update', $package),
            'destroy_url' => route('admin.starter-packages.destroy', $package),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function entityOptions(): array
    {
        return collect(ProjectSkkField::TYPE_LABELS)
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function valueTypeOptions(): array
    {
        return collect(ProjectSkkField::VALUE_TYPE_LABELS)
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }
}

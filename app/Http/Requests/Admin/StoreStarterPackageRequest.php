<?php

namespace App\Http\Requests\Admin;

use App\Models\ProjectSkkField;
use App\Support\F5WidgetCatalog;
use App\Support\FunnelStageColors;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStarterPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => filter_var($this->input('is_active', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
        ]);

        $integrations = $this->input('integrations', []);

        if (is_array($integrations)) {
            foreach ($integrations as $index => $integration) {
                if (! is_array($integration)) {
                    continue;
                }

                if (($integration['widget_slug'] ?? '') === '') {
                    $integrations[$index]['widget_slug'] = null;
                }
            }

            $this->merge(['integrations' => $integrations]);
        }

        $skkFields = $this->input('skk_fields', []);

        if (is_array($skkFields)) {
            foreach ($skkFields as $index => $field) {
                if (! is_array($field)) {
                    continue;
                }

                foreach (['is_required', 'show_in_card', 'show_in_list', 'show_in_filter'] as $boolKey) {
                    if (array_key_exists($boolKey, $field)) {
                        $skkFields[$index][$boolKey] = filter_var(
                            $field[$boolKey],
                            FILTER_VALIDATE_BOOLEAN,
                            FILTER_NULL_ON_FAILURE
                        ) ?? false;
                    }
                }
            }

            $this->merge(['skk_fields' => $skkFields]);
        }
    }

    public function rules(): array
    {
        $widgetSlugs = F5WidgetCatalog::slugs();

        return [
            'name' => ['required', 'string', 'max:255'],
            'business_sphere' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'is_active' => ['sometimes', 'boolean'],

            'funnels' => ['nullable', 'array', 'max:20'],
            'funnels.*.name' => ['required', 'string', 'max:255'],
            'funnels.*.description' => ['nullable', 'string', 'max:5000'],
            'funnels.*.stages' => ['nullable', 'array', 'max:50'],
            'funnels.*.stages.*.name' => ['required', 'string', 'max:255'],
            'funnels.*.stages.*.action' => ['nullable', 'string', 'max:5000'],
            'funnels.*.stages.*.comment' => ['nullable', 'string', 'max:5000'],
            'funnels.*.stages.*.stage_type' => ['nullable', 'string', 'max:255'],
            'funnels.*.stages.*.deadline' => ['nullable', 'string', 'max:255'],
            'funnels.*.stages.*.kind' => ['nullable', 'string', Rule::in(\App\Support\FunnelStageOutcomes::KINDS)],
            'funnels.*.stages.*.outcomes' => ['nullable', 'array', 'max:30'],
            'funnels.*.stages.*.outcomes.*.result' => ['required_with:funnels.*.stages.*.outcomes', 'string', 'max:1000'],
            'funnels.*.stages.*.outcomes.*.mode' => ['required_with:funnels.*.stages.*.outcomes', 'string', Rule::in(\App\Support\FunnelStageOutcomes::MODES)],
            'funnels.*.stages.*.outcomes.*.label' => ['nullable', 'string', 'max:1000'],
            'funnels.*.stages.*.outcomes.*.widget_slug' => ['nullable', 'string', 'max:255'],
            'funnels.*.stages.*.outcomes.*.target_sort_order' => ['nullable', 'integer', 'min:1', 'max:50'],
            'funnels.*.stages.*.color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],

            'skk_fields' => ['nullable', 'array', 'max:100'],
            'skk_fields.*.entity_type' => ['required', 'string', Rule::in(ProjectSkkField::TYPES)],
            'skk_fields.*.name' => ['required', 'string', 'max:255'],
            'skk_fields.*.value_type' => ['nullable', 'string', Rule::in(ProjectSkkField::VALUE_TYPES)],
            'skk_fields.*.is_required' => ['sometimes', 'boolean'],
            'skk_fields.*.show_in_card' => ['sometimes', 'boolean'],
            'skk_fields.*.show_in_list' => ['sometimes', 'boolean'],
            'skk_fields.*.show_in_filter' => ['sometimes', 'boolean'],
            'skk_fields.*.options' => ['nullable', 'array', 'max:100'],
            'skk_fields.*.options.*' => ['string', 'max:255'],
            'skk_fields.*.funnel_name' => ['nullable', 'string', 'max:255'],
            'skk_fields.*.stage_name' => ['nullable', 'string', 'max:255'],
            'skk_fields.*.comment' => ['nullable', 'string', 'max:10000'],

            'integrations' => ['nullable', 'array', 'max:50'],
            'integrations.*.widget_slug' => ['nullable', 'string', Rule::in($widgetSlugs)],
            'integrations.*.name' => ['nullable', 'string', 'max:255'],
            'integrations.*.comment' => ['nullable', 'string', 'max:5000'],
            'integrations.*.billing_quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],

            'work_stages' => ['nullable', 'array', 'max:50'],
            'work_stages.*.name' => ['required', 'string', 'max:255'],
            'work_stages.*.composition' => ['nullable', 'string', 'max:10000'],
            'work_stages.*.cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'work_stages.*.duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('skk_fields', []) as $index => $field) {
                if (! is_array($field)) {
                    continue;
                }

                $valueType = $field['value_type'] ?? ProjectSkkField::VALUE_TEXT;
                $options = ProjectSkkField::normalizeOptions($field['options'] ?? null, $valueType);

                if (in_array($valueType, [ProjectSkkField::VALUE_SELECT, ProjectSkkField::VALUE_MULTISELECT], true)
                    && ($options === null || $options === [])
                ) {
                    $validator->errors()->add("skk_fields.{$index}.options", 'Укажите варианты списка.');
                }
            }

            foreach ($this->input('integrations', []) as $index => $integration) {
                if (! is_array($integration)) {
                    continue;
                }

                $slug = trim((string) ($integration['widget_slug'] ?? ''));
                $name = trim((string) ($integration['name'] ?? ''));

                if ($slug === '' && $name === '') {
                    $validator->errors()->add(
                        "integrations.{$index}.widget_slug",
                        'Выберите виджет или укажите название.'
                    );
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function packagePayload(): array
    {
        $funnels = [];

        foreach ($this->input('funnels', []) as $funnelIndex => $funnel) {
            $name = trim((string) ($funnel['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $stages = [];

            foreach ($funnel['stages'] ?? [] as $stageIndex => $stage) {
                $stageName = trim((string) ($stage['name'] ?? ''));

                if ($stageName === '') {
                    continue;
                }

                $stages[] = [
                    'name' => $stageName,
                    'action' => filled($stage['action'] ?? null) ? trim((string) $stage['action']) : null,
                    'comment' => filled($stage['comment'] ?? null) ? trim((string) $stage['comment']) : null,
                    'stage_type' => filled($stage['stage_type'] ?? null) ? trim((string) $stage['stage_type']) : null,
                    'deadline' => filled($stage['deadline'] ?? null) ? trim((string) $stage['deadline']) : null,
                    'kind' => in_array($stage['kind'] ?? null, \App\Support\FunnelStageOutcomes::KINDS, true)
                        ? (string) $stage['kind']
                        : 'normal',
                    'outcomes' => \App\Support\FunnelStageOutcomes::normalize($stage['outcomes'] ?? []),
                    'color' => filled($stage['color'] ?? null)
                        ? (string) $stage['color']
                        : FunnelStageColors::defaultForIndex($stageIndex),
                ];
            }

            $funnels[] = [
                'name' => $name,
                'description' => filled($funnel['description'] ?? null) ? trim((string) $funnel['description']) : null,
                'stages' => $stages,
            ];
        }

        $skkFields = [];

        foreach ($this->input('skk_fields', []) as $field) {
            $name = trim((string) ($field['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $skkFields[] = [
                'entity_type' => $field['entity_type'] ?? ProjectSkkField::TYPE_DEALS,
                'name' => $name,
                'value_type' => $field['value_type'] ?? ProjectSkkField::VALUE_TEXT,
                'is_required' => (bool) ($field['is_required'] ?? false),
                'show_in_card' => (bool) ($field['show_in_card'] ?? true),
                'show_in_list' => (bool) ($field['show_in_list'] ?? false),
                'show_in_filter' => (bool) ($field['show_in_filter'] ?? false),
                'options' => $field['options'] ?? null,
                'funnel_name' => $field['funnel_name'] ?? null,
                'stage_name' => $field['stage_name'] ?? null,
                'comment' => $field['comment'] ?? null,
            ];
        }

        $integrations = [];

        foreach ($this->input('integrations', []) as $integration) {
            $slug = trim((string) ($integration['widget_slug'] ?? ''));
            $name = trim((string) ($integration['name'] ?? ''));

            if ($slug === '' && $name === '') {
                continue;
            }

            $integrations[] = [
                'widget_slug' => $slug !== '' ? $slug : null,
                'name' => $name !== '' ? $name : null,
                'comment' => $integration['comment'] ?? null,
                'billing_quantity' => $integration['billing_quantity'] ?? null,
            ];
        }

        $workStages = [];

        foreach ($this->input('work_stages', []) as $stage) {
            $name = trim((string) ($stage['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $workStages[] = [
                'name' => $name,
                'composition' => $stage['composition'] ?? null,
                'cost' => $stage['cost'] ?? null,
                'duration_days' => filled($stage['duration_days'] ?? null)
                    ? (int) $stage['duration_days']
                    : null,
            ];
        }

        return [
            'name' => trim((string) $this->input('name')),
            'business_sphere' => trim((string) $this->input('business_sphere')),
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
            'sort_order' => (int) ($this->input('sort_order') ?? 0),
            'is_active' => (bool) $this->input('is_active', true),
            'funnels' => $funnels,
            'skk_fields' => $skkFields,
            'integrations' => $integrations,
            'work_stages' => $workStages,
        ];
    }
}

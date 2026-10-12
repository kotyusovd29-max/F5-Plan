<?php

namespace App\Http\Requests\Concerns;

use App\Models\Project;
use App\Models\ProjectSkkField;
use Illuminate\Validation\Rule;

trait ValidatesSkkFieldAttributes
{
    /**
     * @return array<string, mixed>
     */
    protected function skkFieldAttributeRules(?Project $project = null): array
    {
        $stageRule = ['nullable', 'integer'];

        if ($project) {
            $funnelIds = $project->funnels()->pluck('id');

            $stageRule[] = Rule::exists('funnel_stages', 'id')->where(
                fn ($query) => $query->whereIn('funnel_id', $funnelIds)
            );
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:10000'],
            'value_type' => ['nullable', 'string', Rule::in(ProjectSkkField::VALUE_TYPES)],
            'is_required' => ['sometimes', 'boolean'],
            'show_in_card' => ['sometimes', 'boolean'],
            'show_in_list' => ['sometimes', 'boolean'],
            'show_in_filter' => ['sometimes', 'boolean'],
            'options' => ['nullable', 'array', 'max:100'],
            'options.*' => ['string', 'max:255'],
            'funnel_stage_id' => $stageRule,
        ];
    }

    protected function prepareSkkFieldBooleans(): void
    {
        foreach (['is_required', 'show_in_card', 'show_in_list', 'show_in_filter'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedSkkFieldAttributes(): array
    {
        return ProjectSkkField::normalizeAttributes($this->validated());
    }
}

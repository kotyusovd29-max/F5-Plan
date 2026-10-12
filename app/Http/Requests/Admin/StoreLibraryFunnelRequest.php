<?php

namespace App\Http\Requests\Admin;

use App\Support\FunnelStageColors;
use App\Support\FunnelStageOutcomes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLibraryFunnelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'stages' => ['nullable', 'array', 'max:50'],
            'stages.*.name' => ['required', 'string', 'max:255'],
            'stages.*.action' => ['nullable', 'string', 'max:5000'],
            'stages.*.comment' => ['nullable', 'string', 'max:5000'],
            'stages.*.stage_type' => ['nullable', 'string', 'max:255'],
            'stages.*.deadline' => ['nullable', 'string', 'max:255'],
            'stages.*.kind' => ['nullable', 'string', Rule::in(FunnelStageOutcomes::KINDS)],
            'stages.*.outcomes' => ['nullable', 'array', 'max:30'],
            'stages.*.outcomes.*.result' => ['required_with:stages.*.outcomes', 'string', 'max:1000'],
            'stages.*.outcomes.*.mode' => ['required_with:stages.*.outcomes', 'string', Rule::in(FunnelStageOutcomes::MODES)],
            'stages.*.outcomes.*.label' => ['nullable', 'string', 'max:1000'],
            'stages.*.outcomes.*.widget_slug' => ['nullable', 'string', 'max:255'],
            'stages.*.outcomes.*.widget_comment' => ['nullable', 'string', 'max:2000'],
            'stages.*.outcomes.*.target_sort_order' => ['nullable', 'integer', 'min:1', 'max:50'],
            'stages.*.color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    /**
     * @return array{name: string, description: ?string, sort_order: int, stages: list<array<string, mixed>>}
     */
    public function funnelPayload(): array
    {
        $stages = [];

        foreach ($this->input('stages', []) as $index => $stage) {
            $name = trim((string) ($stage['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $stages[] = [
                'name' => $name,
                'action' => filled($stage['action'] ?? null) ? trim((string) $stage['action']) : null,
                'comment' => filled($stage['comment'] ?? null) ? trim((string) $stage['comment']) : null,
                'stage_type' => filled($stage['stage_type'] ?? null) ? trim((string) $stage['stage_type']) : null,
                'deadline' => filled($stage['deadline'] ?? null) ? trim((string) $stage['deadline']) : null,
                'kind' => in_array($stage['kind'] ?? null, FunnelStageOutcomes::KINDS, true)
                    ? (string) $stage['kind']
                    : 'normal',
                'outcomes' => FunnelStageOutcomes::normalize($stage['outcomes'] ?? []),
                'color' => filled($stage['color'] ?? null) ? (string) $stage['color'] : FunnelStageColors::defaultForIndex($index),
                'sort_order' => $index + 1,
            ];
        }

        return [
            'name' => trim((string) $this->input('name')),
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
            'sort_order' => (int) ($this->input('sort_order') ?? 0),
            'stages' => $stages,
        ];
    }
}

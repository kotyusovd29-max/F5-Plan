<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportAmoCrmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $project = $this->route('project');
        $projectId = $project?->id;

        return [
            'funnel_ids' => ['nullable', 'array'],
            'funnel_ids.*' => [
                'integer',
                'min:1',
                Rule::exists('funnels', 'id')->where(
                    fn ($query) => $query->where('project_id', $projectId)
                ),
            ],
            'include_fields' => ['sometimes', 'boolean'],
            'field_ids' => ['nullable', 'array'],
            'field_ids.*' => [
                'integer',
                'min:1',
                Rule::exists('project_skk_fields', 'id')->where(
                    fn ($query) => $query->where('project_id', $projectId)
                ),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            // Пустой POST — полная выгрузка (обратная совместимость).
            if (
                ! $this->exists('funnel_ids')
                && ! $this->exists('include_fields')
                && ! $this->exists('field_ids')
            ) {
                return;
            }

            $funnelIds = $this->input('funnel_ids', []);
            $includeFields = $this->boolean('include_fields', true);
            $fieldIds = $this->input('field_ids');

            $hasFunnels = is_array($funnelIds) && $funnelIds !== [];
            $hasFields = $includeFields && (
                $fieldIds === null
                || (is_array($fieldIds) && $fieldIds !== [])
            );

            if (! $hasFunnels && ! $hasFields) {
                $validator->errors()->add(
                    'funnel_ids',
                    'Выберите хотя бы одну воронку или поле для выгрузки.'
                );
            }
        });
    }

    /**
     * @return array{funnel_ids: list<int>|null, include_fields: bool, field_ids: list<int>|null}
     */
    public function exportOptions(): array
    {
        $fullExport = ! $this->exists('funnel_ids')
            && ! $this->exists('include_fields')
            && ! $this->exists('field_ids');

        if ($fullExport) {
            return [
                'funnel_ids' => null,
                'include_fields' => true,
                'field_ids' => null,
            ];
        }

        $funnelIds = $this->input('funnel_ids');
        $fieldIds = $this->input('field_ids');

        return [
            'funnel_ids' => is_array($funnelIds)
                ? array_values(array_map('intval', $funnelIds))
                : [],
            'include_fields' => $this->boolean('include_fields', true),
            'field_ids' => is_array($fieldIds)
                ? array_values(array_map('intval', $fieldIds))
                : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'funnel_ids.*.exists' => 'Выбрана воронка, которой нет в этом проекте.',
            'field_ids.*.exists' => 'Выбрано поле, которого нет в этом проекте.',
        ];
    }
}

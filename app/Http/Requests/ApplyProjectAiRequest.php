<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyProjectAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['required', 'string', Rule::in(['skk_fields', 'integrations', 'funnels'])],
            'proposal' => ['required', 'array'],
            'proposal.skk_fields' => ['nullable', 'array'],
            'proposal.skk_fields.deals' => ['nullable', 'array'],
            'proposal.skk_fields.contacts' => ['nullable', 'array'],
            'proposal.skk_fields.companies' => ['nullable', 'array'],
            'proposal.integrations' => ['nullable', 'array'],
            'proposal.funnels' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sections.required' => 'Выберите хотя бы один раздел для применения.',
            'proposal.required' => 'Нет данных для применения. Сначала сгенерируйте предложение.',
        ];
    }
}

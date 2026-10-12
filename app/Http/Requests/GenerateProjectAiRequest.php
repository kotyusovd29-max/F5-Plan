<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateProjectAiRequest extends FormRequest
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
        return [
            'company_name' => ['nullable', 'string', 'max:255'],
            'employees_count' => ['nullable', 'integer', 'min:1'],
            'client_tasks' => ['nullable', 'string', 'max:10000'],
            'extra_instructions' => ['nullable', 'string', 'max:10000'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['required', 'string', Rule::in(['skk_fields', 'integrations', 'funnels'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sections.required' => 'Выберите хотя бы один раздел для генерации.',
            'sections.min' => 'Выберите хотя бы один раздел для генерации.',
        ];
    }
}

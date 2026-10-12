<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportAmoCrmPipelinesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'pipeline_ids' => ['nullable', 'array'],
            'pipeline_ids.*' => ['integer', 'min:1'],
            'include_unsorted' => ['sometimes', 'boolean'],
            'include_system' => ['sometimes', 'boolean'],
            'include_fields' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $pipelineIds = $this->input('pipeline_ids', []);
            $includeFields = $this->boolean('include_fields', true);

            if ((! is_array($pipelineIds) || $pipelineIds === []) && ! $includeFields) {
                $validator->errors()->add(
                    'pipeline_ids',
                    'Выберите хотя бы одну воронку или включите импорт полей СКК.'
                );
            }
        });
    }
}

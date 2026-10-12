<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditAmoCrmAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'pipeline_ids' => ['required', 'array', 'min:1'],
            'pipeline_ids.*' => ['integer', 'min:1'],
            'include_fields' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'pipeline_ids.required' => 'Выберите хотя бы одну воронку для аудита.',
            'pipeline_ids.min' => 'Выберите хотя бы одну воронку для аудита.',
        ];
    }
}

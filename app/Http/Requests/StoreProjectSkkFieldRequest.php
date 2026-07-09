<?php

namespace App\Http\Requests;

use App\Models\ProjectSkkField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectSkkFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', Rule::in(ProjectSkkField::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название поля.',
            'entity_type.required' => 'Укажите тип сущности.',
        ];
    }
}

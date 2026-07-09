<?php

namespace App\Http\Requests;

use App\Models\ProjectSkkField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderSkkFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', Rule::in(ProjectSkkField::TYPES)],
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'integer', 'distinct'],
        ];
    }
}

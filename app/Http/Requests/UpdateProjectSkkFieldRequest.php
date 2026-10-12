<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesSkkFieldAttributes;
use App\Models\ProjectSkkField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProjectSkkFieldRequest extends FormRequest
{
    use ValidatesSkkFieldAttributes;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareSkkFieldBooleans();
    }

    public function rules(): array
    {
        return $this->skkFieldAttributeRules($this->route('project'));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateOptionsForValueType($validator);
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название поля.',
            'options.required' => 'Укажите варианты значений для списка.',
            'funnel_stage_id.exists' => 'Выберите этап из воронок этого проекта.',
        ];
    }

    private function validateOptionsForValueType(Validator $validator): void
    {
        $valueType = $this->input('value_type', ProjectSkkField::VALUE_TEXT);

        if (! in_array($valueType, [ProjectSkkField::VALUE_SELECT, ProjectSkkField::VALUE_MULTISELECT], true)) {
            return;
        }

        $options = ProjectSkkField::normalizeOptions($this->input('options'), $valueType);

        if ($options === null || $options === []) {
            $validator->errors()->add('options', 'Укажите варианты значений для списка.');
        }
    }
}

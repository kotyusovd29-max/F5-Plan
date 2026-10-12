<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesSkkFieldAttributes;
use App\Models\LibrarySkkField;
use App\Models\ProjectSkkField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLibrarySkkFieldRequest extends FormRequest
{
    use ValidatesSkkFieldAttributes;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareSkkFieldBooleans();
    }

    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', Rule::in(ProjectSkkField::TYPES)],
            'stage_name' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            ...$this->skkFieldAttributeRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $valueType = $this->input('value_type', ProjectSkkField::VALUE_TEXT);
            $options = ProjectSkkField::normalizeOptions($this->input('options'), $valueType);

            if (in_array($valueType, [ProjectSkkField::VALUE_SELECT, ProjectSkkField::VALUE_MULTISELECT], true)
                && ($options === null || $options === [])
            ) {
                $validator->errors()->add('options', 'Укажите варианты списка.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function fieldPayload(): array
    {
        $payload = LibrarySkkField::normalizeAttributes([
            ...$this->validated(),
            'entity_type' => $this->input('entity_type'),
            'stage_name' => $this->input('stage_name'),
            'sort_order' => $this->input('sort_order', 0),
        ]);

        $payload['sort_order'] = (int) ($this->input('sort_order') ?? 0);

        return $payload;
    }
}

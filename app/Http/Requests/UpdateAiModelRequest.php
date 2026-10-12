<?php

namespace App\Http\Requests;

use App\Support\F5AiModels;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('ai_model') && $this->input('ai_model') === '') {
            $this->merge(['ai_model' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'ai_model' => ['nullable', 'string', 'max:64', Rule::in(F5AiModels::allowedIds())],
        ];
    }

    public function messages(): array
    {
        return [
            'ai_model.in' => 'Выберите модель из списка доступных.',
        ];
    }
}

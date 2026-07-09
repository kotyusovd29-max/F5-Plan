<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'employees_count' => ['nullable', 'integer', 'min:1'],
            'client_tasks' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required' => 'Укажите название компании.',
            'employees_count.integer' => 'Количество сотрудников должно быть числом.',
            'employees_count.min' => 'Количество сотрудников должно быть не менее 1.',
        ];
    }
}

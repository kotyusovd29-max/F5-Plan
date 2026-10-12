<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'website' => ['nullable', 'string', 'max:255'],
            'business_sphere' => ['nullable', 'string', 'max:255'],
            'current_services' => ['nullable', 'string', 'max:5000'],
            'employees_count' => ['nullable', 'integer', 'min:1'],
            'client_tasks' => ['nullable', 'string', 'max:5000'],
            'amocrm_deal_url' => ['nullable', 'string', 'max:2048'],
            'starter_package_id' => [
                'nullable',
                'integer',
                Rule::exists('starter_packages', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required' => 'Укажите название компании.',
            'employees_count.integer' => 'Количество сотрудников должно быть числом.',
            'employees_count.min' => 'Количество сотрудников должно быть не менее 1.',
            'amocrm_deal_url.max' => 'Ссылка на сделку слишком длинная.',
            'starter_package_id.exists' => 'Выбранный стартовый пакет недоступен.',
        ];
    }
}

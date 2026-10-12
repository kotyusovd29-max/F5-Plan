<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectWorkStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'composition' => ['nullable', 'string', 'max:10000'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название этапа.',
            'cost.numeric' => 'Стоимость должна быть числом.',
            'cost.min' => 'Стоимость не может быть отрицательной.',
            'duration_days.integer' => 'Срок реализации должен быть целым числом рабочих дней.',
            'duration_days.min' => 'Срок реализации должен быть не меньше 1 рабочего дня.',
        ];
    }

    /**
     * @return array{name: string, composition: ?string, cost: ?string, duration_days: ?int}
     */
    public function stagePayload(): array
    {
        $data = $this->validated();

        return [
            'name' => trim((string) $data['name']),
            'composition' => filled($data['composition'] ?? null) ? trim((string) $data['composition']) : null,
            'cost' => array_key_exists('cost', $data) && $data['cost'] !== null && $data['cost'] !== ''
                ? $data['cost']
                : null,
            'duration_days' => array_key_exists('duration_days', $data) && $data['duration_days'] !== null && $data['duration_days'] !== ''
                ? (int) $data['duration_days']
                : null,
        ];
    }
}

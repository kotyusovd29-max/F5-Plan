<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesFunnelStageAttributes;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFunnelStageRequest extends FormRequest
{
    use ValidatesFunnelStageAttributes;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'action' => ['nullable', 'string', 'max:10000'],
            'comment' => ['nullable', 'string', 'max:10000'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ...$this->funnelStageAttributeRules($this->funnelId()),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название этапа.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('outcomes') && is_string($this->input('outcomes'))) {
            $decoded = json_decode($this->input('outcomes'), true);
            $this->merge(['outcomes' => is_array($decoded) ? $decoded : []]);
        }

        if ($this->has('widgets') && is_string($this->input('widgets'))) {
            $decoded = json_decode($this->input('widgets'), true);
            $this->merge(['widgets' => is_array($decoded) ? $decoded : []]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function stageAttributes(): array
    {
        $data = $this->normalizeFunnelStageAttributes(
            $this->safe()->only([
                'name',
                'action',
                'comment',
                'stage_type',
                'deadline',
                'kind',
                'outcomes',
                'widgets',
                'color',
            ])
        );

        if (! array_key_exists('color', $data) || $data['color'] === null) {
            unset($data['color']);
        }

        return $data;
    }

    private function funnelId(): int
    {
        $funnel = $this->route('funnel');

        return $funnel instanceof \App\Models\Funnel ? $funnel->id : (int) $funnel;
    }
}

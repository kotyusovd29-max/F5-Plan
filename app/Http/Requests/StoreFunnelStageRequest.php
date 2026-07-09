<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFunnelStageRequest extends FormRequest
{
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
            'after_stage_id' => [
                'nullable',
                'integer',
                Rule::exists('funnel_stages', 'id')->where('funnel_id', $this->funnelId()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название этапа.',
        ];
    }

    private function funnelId(): int
    {
        $funnel = $this->route('funnel');

        return $funnel instanceof \App\Models\Funnel ? $funnel->id : (int) $funnel;
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Funnel;
use App\Support\BoardShare;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicFunnelBoardCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $funnel = $this->funnel();

        return [
            'author_name' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:3000'],
            'scope' => ['nullable', 'string', Rule::in(['board', 'stage'])],
            'funnel_stage_id' => [
                Rule::requiredIf(fn () => $this->input('scope') === 'stage'),
                'nullable',
                'integer',
                $funnel
                    ? Rule::exists('funnel_stages', 'id')->where('funnel_id', $funnel->id)
                    : 'integer',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'author_name.required' => 'Укажите имя.',
            'body.required' => 'Напишите комментарий.',
            'body.max' => 'Комментарий слишком длинный (макс. 3000 символов).',
            'funnel_stage_id.exists' => 'Выбранный этап не найден на этой доске.',
        ];
    }

    public function funnel(): ?Funnel
    {
        $token = (string) $this->route('token');

        if ($token === '') {
            return null;
        }

        $resolved = BoardShare::resolve($token);

        if ($resolved === null) {
            return null;
        }

        $funnelId = $this->integer('funnel') ?: null;

        try {
            return BoardShare::activeFunnel(
                $resolved['project'],
                $funnelId,
                $resolved['legacy_funnel']
            );
        } catch (\Throwable) {
            return null;
        }
    }

    protected function prepareForValidation(): void
    {
        $scope = $this->input('scope', 'board');

        if ($scope === 'board') {
            $this->merge(['funnel_stage_id' => null]);
        }
    }
}

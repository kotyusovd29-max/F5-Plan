<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectEditorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $project->canManageEditors($this->user());
    }

    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('id', '!=', $project->user_id)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.*.exists' => 'Выбран некорректный пользователь.',
        ];
    }
}

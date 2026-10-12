<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use App\Support\F5AiModels;

class AiChatPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function projectForChat(Project $project): array
    {
        return [
            'id' => $project->id,
            'company_name' => $project->company_name,
            'urls' => [
                'show' => route('projects.show', $project),
                ...ProjectAiUrls::forProject($project),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function projectsForUser(User $user): array
    {
        return $user->projects()
            ->withoutTraining()
            ->latest()
            ->get()
            ->map(fn (Project $project) => self::projectForChat($project))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function sharedPayload(User $user): array
    {
        return [
            'projects' => self::projectsForUser($user),
            'prompts' => [
                'analysis' => ProjectAiPrompts::analysis(),
                'actions' => ProjectAiPrompts::actions(),
                'meetingActions' => ProjectAiPrompts::meetingActions(),
                'auditRecommendationActions' => ProjectAiPrompts::auditRecommendationActions(),
            ],
            'models' => self::modelsPayload($user),
        ];
    }

    /**
     * @return array{
     *     selected: string|null,
     *     selected_label: string|null,
     *     default: string,
     *     default_label: string,
     *     options: list<array{id: string, label: string, vendor: string, description: string|null}>,
     *     update_url: string
     * }
     */
    public static function modelsPayload(User $user): array
    {
        $default = (string) config('f5ai.model', 'gpt-5.5');
        $selected = F5AiModels::isAllowed($user->ai_model) ? (string) $user->ai_model : null;

        return [
            'selected' => $selected,
            'selected_label' => $selected
                ? (F5AiModels::labelFor($selected) ?? $selected)
                : null,
            'default' => $default,
            'default_label' => F5AiModels::labelFor($default) ?? $default,
            'options' => F5AiModels::optionsForFrontend(),
            'update_url' => route('profile.ai-model'),
        ];
    }
}

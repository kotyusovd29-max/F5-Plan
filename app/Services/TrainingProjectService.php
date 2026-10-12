<?php

namespace App\Services;

use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\ProjectIntegration;
use App\Models\ProjectSkkField;
use App\Models\ProjectWorkStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TrainingProjectService
{
    public const COMPANY_NAME = 'Учебный проект F5 Plan';

    public function find(): ?Project
    {
        return Project::query()
            ->withTrashed()
            ->training()
            ->orderBy('id')
            ->first();
    }

    public function ensure(): Project
    {
        $existing = $this->find();

        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            if (! $existing->is_training) {
                $existing->update(['is_training' => true]);
            }

            return $existing->fresh() ?? $existing;
        }

        return DB::transaction(function (): Project {
            $owner = $this->resolveOwner();

            $project = Project::query()->create([
                'user_id' => $owner->id,
                'company_name' => self::COMPANY_NAME,
                'website' => 'https://example.f5plan.local',
                'business_sphere' => 'B2B-услуги / обучение',
                'current_services' => 'amoCRM, телефония, сайт',
                'employees_count' => 12,
                'client_tasks' => 'Демонстрационный план для обучения консультантов: воронки, поля, интеграции, смета и работа с ИИ.',
                'status' => Project::STATUS_APPROVED,
                'is_training' => true,
            ]);

            $this->seedDemoContent($project);

            return $project;
        });
    }

    /**
     * @return array{
     *     project_id: int,
     *     company_name: string,
     *     show_url: string,
     *     ai_chat_url: string,
     *     canvas_url: string,
     *     documents_url: string,
     *     start_url: string
     * }
     */
    public function sharedPayload(): array
    {
        $project = $this->ensure();

        return [
            'project_id' => $project->id,
            'company_name' => $project->company_name,
            'show_url' => route('projects.show', $project),
            'ai_chat_url' => route('projects.ai.chat', $project),
            'canvas_url' => route('projects.canvas.show', $project),
            'documents_url' => route('projects.documents.index', $project),
            'start_url' => route('projects.show', [
                'project' => $project,
                'tour' => 1,
            ]),
        ];
    }

    private function resolveOwner(): User
    {
        $admin = User::query()
            ->where(function ($query): void {
                $query->where('role', User::ROLE_ADMIN)
                    ->orWhere('is_admin', true);
            })
            ->orderBy('id')
            ->first();

        if ($admin !== null) {
            return $admin;
        }

        return User::query()->orderBy('id')->firstOrFail();
    }

    private function seedDemoContent(Project $project): void
    {
        $funnel = Funnel::query()->create([
            'project_id' => $project->id,
            'name' => 'Продажи B2B',
            'description' => 'Учебная воронка: от лида до сделки',
            'sort_order' => 1,
        ]);

        $stages = [
            ['name' => 'Новый лид', 'action' => 'Позвонить за 15 минут', 'deadline' => '15 минут', 'sort_order' => 1],
            ['name' => 'Квалификация', 'action' => 'Выяснить потребность и бюджет', 'deadline' => '1 день', 'sort_order' => 2],
            ['name' => 'КП', 'action' => 'Отправить коммерческое предложение', 'deadline' => '2 дня', 'sort_order' => 3],
            ['name' => 'Успешно реализовано', 'action' => 'Передать в внедрение', 'deadline' => '', 'sort_order' => 4, 'kind' => 'win'],
        ];

        foreach ($stages as $stage) {
            FunnelStage::query()->create([
                'funnel_id' => $funnel->id,
                'name' => $stage['name'],
                'action' => $stage['action'],
                'deadline' => $stage['deadline'] !== '' ? $stage['deadline'] : null,
                'kind' => $stage['kind'] ?? 'normal',
                'sort_order' => $stage['sort_order'],
                'canvas_pos_x' => 80 + (($stage['sort_order'] - 1) * 300),
                'canvas_pos_y' => 200,
            ]);
        }

        foreach ([
            ['entity_type' => 'deals', 'name' => 'Источник лида', 'value_type' => 'select', 'options' => ['Сайт', 'Реклама', 'Рекомендация']],
            ['entity_type' => 'contacts', 'name' => 'Должность', 'value_type' => 'text', 'options' => []],
            ['entity_type' => 'companies', 'name' => 'Отрасль', 'value_type' => 'text', 'options' => []],
        ] as $index => $field) {
            ProjectSkkField::query()->create([
                'project_id' => $project->id,
                'entity_type' => $field['entity_type'],
                'name' => $field['name'],
                'value_type' => $field['value_type'],
                'options' => $field['options'],
                'is_required' => false,
                'show_in_card' => true,
                'show_in_list' => true,
                'show_in_filter' => true,
                'comment' => 'Учебное поле',
                'sort_order' => $index + 1,
            ]);
        }

        ProjectIntegration::query()->create([
            'project_id' => $project->id,
            'name' => 'Chatter',
            'widget_slug' => 'chatter',
            'comment' => 'Учебный пример интеграции мессенджеров',
            'sort_order' => 1,
        ]);

        foreach ([
            ['name' => 'Аналитика', 'composition' => 'Обследование процессов и проектирование', 'cost' => 80000, 'duration_days' => 10],
            ['name' => 'Настройка amoCRM', 'composition' => 'Воронки, поля, права, базовые автоматизации', 'cost' => 120000, 'duration_days' => 15],
        ] as $index => $stage) {
            ProjectWorkStage::query()->create([
                'project_id' => $project->id,
                'name' => $stage['name'],
                'composition' => $stage['composition'],
                'cost' => $stage['cost'],
                'duration_days' => $stage['duration_days'],
                'sort_order' => $index + 1,
            ]);
        }
    }
}

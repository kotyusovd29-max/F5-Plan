<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectAiChangesApplier;
use App\Support\ProjectAiPrompts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAiAutoTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_tasks_action_is_available_in_prompts(): void
    {
        $actions = collect(ProjectAiPrompts::actions());
        $autoTasks = $actions->first(fn (array $action) => $action['mode'] === 'auto_tasks');

        $this->assertNotNull($autoTasks);
        $this->assertStringContainsString('НЕ включай виджеты', $autoTasks['message']);
        $this->assertStringContainsString('явно попросит', $autoTasks['message']);
    }

    public function test_applier_writes_auto_task_fields_and_outcomes(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Продажи',
            'sort_order' => 1,
        ]);

        $lead = FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => 'Новый лид',
            'action' => 'Старое',
            'sort_order' => 1,
        ]);

        $qualified = FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => 'Квалификация',
            'sort_order' => 2,
        ]);

        $counts = app(ProjectAiChangesApplier::class)->apply($project, [
            'stages' => [
                'update' => [[
                    'id' => $lead->id,
                    'action' => 'Позвонить клиенту в течение 15 минут',
                    'deadline' => '15 минут',
                    'stage_type' => 'Первичный контакт',
                    'kind' => 'normal',
                    'outcomes' => [
                        [
                            'result' => 'Дозвонился',
                            'mode' => 'move',
                            'target_stage_id' => $qualified->id,
                        ],
                        [
                            'result' => 'Недозвон',
                            'mode' => 'stay',
                        ],
                    ],
                ]],
                'create' => [],
                'delete' => [],
            ],
        ]);

        $this->assertSame(1, $counts['updated']);

        $lead->refresh();

        $this->assertSame('Позвонить клиенту в течение 15 минут', $lead->action);
        $this->assertSame('15 минут', $lead->deadline);
        $this->assertSame('Первичный контакт', $lead->stage_type);
        $this->assertSame('normal', $lead->kind);
        $this->assertCount(2, $lead->outcomes);
        $this->assertSame($qualified->id, $lead->outcomes[0]['target_stage_id']);
        $this->assertSame('stay', $lead->outcomes[1]['mode']);
    }

    public function test_applier_creates_funnel_stages_with_outcome_sort_order(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        app(ProjectAiChangesApplier::class)->apply($project, [
            'funnels' => [
                'create' => [[
                    'name' => 'Входящие',
                    'description' => 'Лиды',
                    'stages' => [
                        [
                            'name' => 'Новый',
                            'action' => 'Взять в работу',
                            'deadline' => '1 час',
                            'outcomes' => [[
                                'result' => 'Взял',
                                'mode' => 'move',
                                'target_sort_order' => 2,
                            ]],
                        ],
                        [
                            'name' => 'В работе',
                            'action' => 'Квалифицировать',
                            'deadline' => '1 день',
                        ],
                    ],
                ]],
                'update' => [],
                'delete' => [],
            ],
        ]);

        $funnel = $project->funnels()->where('name', 'Входящие')->first();
        $this->assertNotNull($funnel);

        $stages = $funnel->stages()->orderBy('sort_order')->get();
        $this->assertCount(2, $stages);
        $this->assertSame('Взять в работу', $stages[0]->action);
        $this->assertSame('1 час', $stages[0]->deadline);
        $this->assertSame($stages[1]->id, $stages[0]->outcomes[0]['target_stage_id']);
    }
}

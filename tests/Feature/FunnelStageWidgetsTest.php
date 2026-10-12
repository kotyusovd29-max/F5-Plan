<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\User;
use App\Support\F5WidgetCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelStageWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_stage_store_and_update_persist_widgets_and_outcome_widget(): void
    {
        $widget = collect(F5WidgetCatalog::widgets())->first(
            fn (array $item): bool => ($item['slug'] ?? '') === 'telegram-amocrm'
        );
        $this->assertNotNull($widget);

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Widgets Co',
        ]);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('projects.funnels.stages.store', [$project, $funnel]), [
                'name' => 'Новая заявка',
                'action' => 'Напиши клиенту в Telegram',
                'widgets' => [
                    [
                        'widget_slug' => 'telegram-amocrm',
                        'comment' => 'Шаблон первого касания',
                    ],
                ],
                'outcomes' => [
                    [
                        'result' => 'Ответил',
                        'mode' => 'move',
                        'target_stage_id' => null,
                        'label' => null,
                        'widget_slug' => 'telegram-amocrm',
                        'widget_comment' => 'Отправить шаблон после ответа',
                    ],
                ],
                'kind' => 'normal',
            ])
            ->assertRedirect();

        $stage = FunnelStage::query()->where('name', 'Новая заявка')->first();
        $this->assertNotNull($stage);
        $this->assertCount(1, $stage->widgets);
        $this->assertSame('telegram-amocrm', $stage->widgets[0]['widget_slug']);
        $this->assertSame('Шаблон первого касания', $stage->widgets[0]['comment']);
        $this->assertSame('telegram-amocrm', $stage->outcomes[0]['widget_slug']);
        $this->assertSame('Отправить шаблон после ответа', $stage->outcomes[0]['widget_comment']);

        $this->actingAs($user)
            ->put(route('projects.funnels.stages.update', [$project, $funnel, $stage]), [
                'name' => 'Новая заявка',
                'action' => 'Напиши клиенту',
                'widgets' => [
                    [
                        'widget_slug' => 'telegram-amocrm',
                        'comment' => 'Обновлённый сценарий',
                    ],
                ],
                'outcomes' => [
                    [
                        'result' => 'Не ответил',
                        'mode' => 'stay',
                        'target_stage_id' => $stage->id,
                        'label' => 'Остаётся',
                        'widget_slug' => null,
                    ],
                ],
                'kind' => 'normal',
            ])
            ->assertRedirect();

        $stage->refresh();
        $this->assertSame('Обновлённый сценарий', $stage->widgets[0]['comment']);
        $this->assertNull($stage->outcomes[0]['widget_slug']);
    }

    public function test_outcome_widget_can_be_selected_independently_from_stage_widgets(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Widgets Co',
        ]);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('projects.funnels.stages.store', [$project, $funnel]), [
                'name' => 'Квалификация',
                'action' => 'Запусти чаттер',
                'widgets' => [
                    [
                        'widget_slug' => 'telegram-amocrm',
                        'comment' => 'Только Telegram',
                    ],
                ],
                'outcomes' => [
                    [
                        'result' => 'Успех',
                        'mode' => 'move',
                        'widget_slug' => 'chatter',
                    ],
                ],
                'kind' => 'normal',
            ])
            ->assertRedirect();

        $stage = FunnelStage::query()->where('name', 'Квалификация')->first();
        $this->assertNotNull($stage);
        $this->assertSame('chatter', $stage->outcomes[0]['widget_slug']);
        $this->assertSame('telegram-amocrm', $stage->widgets[0]['widget_slug']);
    }

    public function test_funnel_show_and_canvas_serialize_widgets(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Widgets Co',
        ]);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
        ]);
        FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => 'Контакт',
            'sort_order' => 1,
            'action' => 'Напиши в TG',
            'widgets' => [
                ['widget_slug' => 'telegram-amocrm', 'comment' => 'Шаблон'],
            ],
            'outcomes' => [
                [
                    'result' => 'Ответил',
                    'mode' => 'move',
                    'target_stage_id' => null,
                    'label' => null,
                    'widget_slug' => 'telegram-amocrm',
                ],
            ],
            'kind' => 'normal',
            'color' => '#99ccff',
            'canvas_pos_x' => 10,
            'canvas_pos_y' => 20,
        ]);

        $this->actingAs($user)
            ->get(route('projects.funnels.show', [$project, $funnel]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Funnels/Show')
                ->has('f5WidgetCatalog.widgets')
                ->where('funnel.stages.0.widgets.0.widget_slug', 'telegram-amocrm')
                ->where('funnel.stages.0.widgets.0.comment', 'Шаблон')
                ->where('funnel.stages.0.outcomes.0.widget_slug', 'telegram-amocrm')
            );

        $this->actingAs($user)
            ->get(route('projects.funnels.canvas.show', [$project, $funnel]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Funnels/Canvas')
                ->has('f5WidgetCatalog.widgets')
                ->where('funnel.stages.0.widgets.0.widget_slug', 'telegram-amocrm')
                ->where('funnel.stages.0.outcomes.0.widget_slug', 'telegram-amocrm')
            );
    }
}

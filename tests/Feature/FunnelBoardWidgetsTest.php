<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\User;
use App\Support\F5WidgetCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FunnelBoardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_canvas_save_persists_board_widgets_and_outcome_can_target_widget(): void
    {
        $widget = collect(F5WidgetCatalog::widgets())->first(
            fn (array $item): bool => ($item['slug'] ?? '') === 'telegram-amocrm'
        );
        $this->assertNotNull($widget);

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Board Widgets Co',
        ]);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
        ]);
        $stage = FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => 'Лид',
            'sort_order' => 1,
            'kind' => 'normal',
            'color' => '#99ccff',
            'canvas_pos_x' => 10,
            'canvas_pos_y' => 20,
        ]);
        $closed = FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => 'Закрыто',
            'sort_order' => 2,
            'kind' => 'lose',
            'color' => '#ffcccc',
            'canvas_pos_x' => 400,
            'canvas_pos_y' => 20,
        ]);

        $boardWidgetId = (string) Str::uuid();

        $this->actingAs($user)
            ->putJson(route('projects.funnels.canvas.update', [$project, $funnel]), [
                'board_widgets' => [
                    [
                        'id' => $boardWidgetId,
                        'widget_slug' => 'telegram-amocrm',
                        'comment' => 'Общий пуш',
                        'canvas_pos_x' => 200,
                        'canvas_pos_y' => 180,
                        'next_type' => 'stage',
                        'next_id' => $closed->id,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $funnel->refresh();
        $this->assertCount(1, $funnel->board_widgets);
        $this->assertSame('telegram-amocrm', $funnel->board_widgets[0]['widget_slug']);
        $this->assertSame('stage', $funnel->board_widgets[0]['next_type']);
        $this->assertSame($closed->id, $funnel->board_widgets[0]['next_id']);

        $this->actingAs($user)
            ->put(route('projects.funnels.stages.update', [$project, $funnel, $stage]), [
                'name' => 'Лид',
                'action' => 'Позвони',
                'kind' => 'normal',
                'outcomes' => [
                    [
                        'result' => 'Неуспех',
                        'mode' => 'move',
                        'target_type' => 'widget',
                        'target_widget_id' => $boardWidgetId,
                    ],
                ],
            ])
            ->assertRedirect();

        $stage->refresh();
        $this->assertSame('widget', $stage->outcomes[0]['target_type']);
        $this->assertSame($boardWidgetId, $stage->outcomes[0]['target_widget_id']);

        $this->actingAs($user)
            ->get(route('projects.funnels.canvas.show', [$project, $funnel]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Funnels/Canvas')
                ->has('funnel.board_widgets', 1)
                ->where('funnel.board_widgets.0.widget_slug', 'telegram-amocrm')
                ->where('funnel.board_widgets.0.comment', 'Общий пуш')
                ->where('funnel.stages.0.outcomes.0.target_widget_id', $boardWidgetId)
            );

        $this->actingAs($user)
            ->putJson(route('projects.funnels.canvas.update', [$project, $funnel]), [
                'board_widgets' => [
                    [
                        'id' => $boardWidgetId,
                        'widget_slug' => 'telegram-amocrm',
                        'comment' => 'Сценарий: уведомление РОПу после отказа',
                        'canvas_pos_x' => 200,
                        'canvas_pos_y' => 180,
                        'next_type' => 'stage',
                        'next_id' => $closed->id,
                    ],
                ],
            ])
            ->assertOk();

        $funnel->refresh();
        $this->assertSame('Сценарий: уведомление РОПу после отказа', $funnel->board_widgets[0]['comment']);
    }

    public function test_canvas_persists_arrow_path_points_for_outcomes_and_board_widgets(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Arrow Route Co',
        ]);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
        ]);
        $stage = FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => 'Лид',
            'sort_order' => 1,
            'kind' => 'normal',
            'color' => '#99ccff',
            'canvas_pos_x' => 10,
            'canvas_pos_y' => 20,
        ]);
        $closed = FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => 'Закрыто',
            'sort_order' => 2,
            'kind' => 'lose',
            'color' => '#ffcccc',
            'canvas_pos_x' => 400,
            'canvas_pos_y' => 20,
        ]);
        $boardWidgetId = (string) Str::uuid();

        $this->actingAs($user)
            ->putJson(route('projects.funnels.canvas.update', [$project, $funnel]), [
                'board_widgets' => [
                    [
                        'id' => $boardWidgetId,
                        'widget_slug' => 'telegram-amocrm',
                        'comment' => null,
                        'canvas_pos_x' => 200,
                        'canvas_pos_y' => 180,
                        'next_type' => 'stage',
                        'next_id' => $closed->id,
                        'path_points' => [
                            ['x' => 260, 'y' => 120],
                            ['x' => 340, 'y' => 120],
                        ],
                    ],
                ],
            ])
            ->assertOk();

        $funnel->refresh();
        $this->assertSame(
            [['x' => 260, 'y' => 120], ['x' => 340, 'y' => 120]],
            $funnel->board_widgets[0]['path_points']
        );

        $this->actingAs($user)
            ->put(route('projects.funnels.stages.update', [$project, $funnel, $stage]), [
                'name' => 'Лид',
                'action' => 'Позвони',
                'kind' => 'normal',
                'outcomes' => [
                    [
                        'result' => 'Переход',
                        'mode' => 'move',
                        'target_type' => 'stage',
                        'target_stage_id' => $closed->id,
                        'path_points' => [
                            ['x' => 180, 'y' => 240],
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $stage->refresh();
        $this->assertSame([['x' => 180, 'y' => 240]], $stage->outcomes[0]['path_points']);
    }
}

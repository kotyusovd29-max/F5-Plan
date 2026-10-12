<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelStageMiroFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_stage_store_and_update_persist_miro_fields_and_outcomes(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'HoReCa',
        ]);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => '5.1 HoReCa',
            'sort_order' => 1,
        ]);

        $lose = FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => '( закрыто не реализовано )',
            'sort_order' => 2,
            'kind' => 'lose',
            'color' => '#ffcccc',
        ]);

        $this->actingAs($user)
            ->post(route('projects.funnels.stages.store', [$project, $funnel]), [
                'name' => 'Потенциальный клиент',
                'action' => 'Позвони клиенту.',
                'stage_type' => 'Первичный конт',
                'deadline' => '1 рабочий день',
                'kind' => 'normal',
                'outcomes' => [
                    [
                        'result' => 'Ответили, интерес есть',
                        'mode' => 'move',
                        'target_stage_id' => null,
                        'label' => 'Сделка переходит дальше',
                    ],
                    [
                        'result' => 'Не наш клиент',
                        'mode' => 'lose',
                        'target_stage_id' => $lose->id,
                        'label' => 'В отказ',
                    ],
                ],
            ])
            ->assertRedirect();

        $stage = FunnelStage::query()->where('name', 'Потенциальный клиент')->first();
        $this->assertNotNull($stage);
        $this->assertSame('Первичный конт', $stage->stage_type);
        $this->assertSame('1 рабочий день', $stage->deadline);
        $this->assertSame('normal', $stage->kind);
        $this->assertCount(2, $stage->outcomes);
        $this->assertSame('lose', $stage->outcomes[1]['mode']);
        $this->assertSame($lose->id, $stage->outcomes[1]['target_stage_id']);

        $this->actingAs($user)
            ->put(route('projects.funnels.stages.update', [$project, $funnel, $stage]), [
                'name' => 'Потенциальный клиент',
                'action' => 'Позвони клиенту. Узнай ЛПР.',
                'stage_type' => 'Первичный конт',
                'deadline' => '1 р.д.',
                'kind' => 'normal',
                'outcomes' => [
                    [
                        'result' => 'Не взяли трубку',
                        'mode' => 'stay',
                        'target_stage_id' => $stage->id,
                        'label' => 'Остаётся',
                    ],
                ],
            ])
            ->assertRedirect();

        $stage->refresh();
        $this->assertSame('1 р.д.', $stage->deadline);
        $this->assertCount(1, $stage->outcomes);
        $this->assertSame('stay', $stage->outcomes[0]['mode']);
    }

    public function test_funnel_show_and_canvas_serialize_miro_fields(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'HoReCa',
        ]);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
        ]);
        FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => 'Win',
            'sort_order' => 1,
            'stage_type' => 'Постпродажа',
            'deadline' => '2 дня',
            'kind' => 'win',
            'action' => 'Проверь поставку',
            'outcomes' => [
                ['result' => 'Ок', 'mode' => 'win', 'target_stage_id' => null, 'label' => null],
            ],
            'color' => '#99ff99',
            'canvas_pos_x' => 10,
            'canvas_pos_y' => 20,
        ]);

        $this->actingAs($user)
            ->get(route('projects.funnels.show', [$project, $funnel]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Funnels/Show')
                ->where('funnel.stages.0.stage_type', 'Постпродажа')
                ->where('funnel.stages.0.kind', 'win')
                ->where('funnel.stages.0.outcomes.0.mode', 'win')
            );

        $this->actingAs($user)
            ->get(route('projects.funnels.canvas.show', [$project, $funnel]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Funnels/Canvas')
                ->where('funnel.stages.0.deadline', '2 дня')
                ->where('funnel.stages.0.kind', 'win')
            );
    }
}

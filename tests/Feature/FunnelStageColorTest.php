<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelStageColorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_stage_store_assigns_default_color(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Test Company',
        ]);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('projects.funnels.stages.store', [$project, $funnel]), [
                'name' => 'Новая заявка',
                'action' => 'Позвонить клиенту',
            ])
            ->assertRedirect();

        $stage = FunnelStage::first();
        $this->assertNotNull($stage);
        $this->assertSame('#99ccff', $stage->color);
    }

    public function test_stage_update_persists_selected_color(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Test Company',
        ]);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
        ]);
        $stage = FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => 'Этап 1',
            'sort_order' => 1,
            'color' => '#99ccff',
        ]);

        $this->actingAs($user)
            ->put(route('projects.funnels.stages.update', [$project, $funnel, $stage]), [
                'name' => 'Этап 1',
                'color' => '#ff9999',
            ])
            ->assertRedirect();

        $this->assertSame('#ff9999', $stage->fresh()->color);
        $this->assertSame('#ff9999', $stage->fresh()->barColor());
    }
}

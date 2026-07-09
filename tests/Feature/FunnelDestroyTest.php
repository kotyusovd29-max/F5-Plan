<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\ProjectCanvasBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelDestroyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_funnel_can_be_deleted_with_stages_and_canvas_block(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Test Company',
        ]);

        $firstFunnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Продажи',
            'sort_order' => 1,
        ]);

        $secondFunnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Сервис',
            'sort_order' => 2,
        ]);

        FunnelStage::create([
            'funnel_id' => $firstFunnel->id,
            'name' => 'Новый лид',
            'sort_order' => 1,
        ]);

        ProjectCanvasBlock::create([
            'project_id' => $project->id,
            'block_key' => 'funnel-'.$firstFunnel->id,
            'block_type' => ProjectCanvasBlock::TYPE_FUNNEL,
            'reference_id' => $firstFunnel->id,
            'title' => $firstFunnel->name,
            'pos_x' => 460,
            'pos_y' => 480,
            'width' => 520,
            'z_index' => 10,
        ]);

        $this->actingAs($user)
            ->delete(route('projects.funnels.destroy', [$project, $firstFunnel]))
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseMissing('funnels', ['id' => $firstFunnel->id]);
        $this->assertDatabaseMissing('funnel_stages', ['funnel_id' => $firstFunnel->id]);
        $this->assertDatabaseMissing('project_canvas_blocks', [
            'project_id' => $project->id,
            'block_key' => 'funnel-'.$firstFunnel->id,
        ]);

        $this->assertSame(1, $secondFunnel->fresh()->sort_order);
    }
}

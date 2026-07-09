<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectCanvasBlock;
use App\Models\ProjectIntegration;
use App\Models\ProjectSkkField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanvasBlockDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_deleting_skk_canvas_block_removes_fields(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Test Company',
        ]);

        ProjectSkkField::create([
            'project_id' => $project->id,
            'entity_type' => 'contacts',
            'name' => 'Телефон',
            'sort_order' => 1,
        ]);

        $block = ProjectCanvasBlock::create([
            'project_id' => $project->id,
            'block_key' => 'skk-contacts',
            'block_type' => ProjectCanvasBlock::TYPE_SKK,
            'title' => 'Поля — Контакты',
            'pos_x' => 100,
            'pos_y' => 100,
            'width' => 360,
            'z_index' => 1,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('projects.canvas.blocks.destroy', [$project, $block]), [], [
                'X-Canvas-Request' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('project_skk_fields', [
            'project_id' => $project->id,
            'entity_type' => 'contacts',
        ]);
    }

    public function test_deleting_integrations_canvas_block_removes_integrations(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Test Company',
        ]);

        ProjectIntegration::create([
            'project_id' => $project->id,
            'name' => 'WhatsApp',
            'sort_order' => 1,
        ]);

        $block = ProjectCanvasBlock::create([
            'project_id' => $project->id,
            'block_key' => 'integrations',
            'block_type' => ProjectCanvasBlock::TYPE_INTEGRATIONS,
            'title' => 'Интеграции и виджеты',
            'pos_x' => 100,
            'pos_y' => 100,
            'width' => 360,
            'z_index' => 1,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('projects.canvas.blocks.destroy', [$project, $block]), [], [
                'X-Canvas-Request' => '1',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('project_integrations', [
            'project_id' => $project->id,
        ]);
    }
}

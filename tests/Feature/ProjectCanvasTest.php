<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\Project;
use App\Models\ProjectCanvasBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCanvasTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_project_canvas(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->actingAs($user)
            ->get(route('projects.canvas.show', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Canvas')
                ->where('project.company_name', 'ООО Тест')
                ->has('blocks')
            );
    }

    public function test_canvas_layout_save_endpoint(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $block = ProjectCanvasBlock::query()->create([
            'project_id' => $project->id,
            'block_key' => 'info',
            'block_type' => ProjectCanvasBlock::TYPE_INFO,
            'title' => 'Основная информация',
            'pos_x' => 80,
            'pos_y' => 80,
            'width' => 360,
            'z_index' => 1,
        ]);

        $this->actingAs($user)
            ->putJson(route('projects.canvas.update', $project), [
                'blocks' => [
                    [
                        'id' => $block->id,
                        'pos_x' => 120,
                        'pos_y' => 140,
                        'z_index' => 2,
                    ],
                ],
                'viewport' => [
                    'offset_x' => 10,
                    'offset_y' => 20,
                    'zoom' => 1.25,
                ],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $block->refresh();
        $project->refresh();

        $this->assertSame(120, $block->pos_x);
        $this->assertSame(140, $block->pos_y);
        $this->assertSame(2, $block->z_index);
        $this->assertSame(10, $project->canvas_offset_x);
        $this->assertSame(1.25, (float) $project->canvas_zoom);
    }
}

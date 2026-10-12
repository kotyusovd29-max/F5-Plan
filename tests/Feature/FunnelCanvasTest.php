<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelCanvasTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_funnel_canvas(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);
        $funnel = Funnel::query()->create([
            'project_id' => $project->id,
            'name' => 'Продажи',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('projects.funnels.canvas.show', [$project, $funnel]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Funnels/Canvas')
                ->where('funnel.name', 'Продажи')
                ->has('funnel.stages')
            );
    }
}

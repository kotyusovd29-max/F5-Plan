<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_funnel_page(): void
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
        FunnelStage::query()->create([
            'funnel_id' => $funnel->id,
            'name' => 'Новая заявка',
            'sort_order' => 1,
            'color' => '#99ccff',
        ]);

        $this->actingAs($user)
            ->get(route('projects.funnels.show', [$project, $funnel]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Funnels/Show')
                ->where('funnel.name', 'Продажи')
                ->where('project.company_name', 'ООО Тест')
                ->has('funnel.stages', 1)
                ->where('funnel.stages.0.name', 'Новая заявка')
            );
    }

    public function test_funnel_update_returns_inertia_redirect(): void
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
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->put(route('projects.funnels.update', [$project, $funnel]), [
                'name' => 'Воронка B2B',
                'description' => 'Описание',
            ])
            ->assertRedirect(route('projects.funnels.show', [$project, $funnel]));

        $this->assertSame('Воронка B2B', $funnel->fresh()->name);
    }
}

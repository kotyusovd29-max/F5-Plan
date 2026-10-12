<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Support\RopTeamDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RopTeamDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_view_includes_rop_dashboard(): void
    {
        $rop = User::factory()->rop()->create();
        $employee = User::factory()->employee($rop)->create();

        Project::query()->create([
            'user_id' => $employee->id,
            'company_name' => 'Черновик А',
            'status' => Project::STATUS_DRAFT,
            'status_changed_at' => now()->subDays(10),
        ]);
        Project::query()->create([
            'user_id' => $employee->id,
            'company_name' => 'На согласовании',
            'status' => Project::STATUS_PENDING_APPROVAL,
            'status_changed_at' => now()->subDays(1),
        ]);
        Project::query()->create([
            'user_id' => $rop->id,
            'company_name' => 'Внедрение',
            'status' => Project::STATUS_IN_IMPLEMENTATION,
            'status_changed_at' => now()->subDays(2),
        ]);

        $this->actingAs($rop)
            ->get(route('home', ['view' => 'team']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home/Index')
                ->where('view', 'team')
                ->has('teamDashboard.funnel', 4)
                ->where('teamDashboard.funnel.0.count', 1)
                ->where('teamDashboard.funnel.1.count', 1)
                ->where('teamDashboard.funnel.3.count', 1)
                ->where('teamDashboard.sla.overdue_count', 1)
                ->has('teamDashboard.employees')
            );
    }

    public function test_employee_does_not_get_team_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home', ['view' => 'team']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('view', 'mine')
                ->where('teamDashboard', null)
            );
    }

    public function test_status_change_updates_status_changed_at(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Тест',
            'status' => Project::STATUS_DRAFT,
            'status_changed_at' => now()->subDays(5),
        ]);

        $old = $project->status_changed_at->copy();

        $this->actingAs($user)
            ->put(route('projects.status.update', $project), [
                'status' => Project::STATUS_PENDING_APPROVAL,
            ])
            ->assertRedirect();

        $project->refresh();

        $this->assertSame(Project::STATUS_PENDING_APPROVAL, $project->status);
        $this->assertTrue($project->status_changed_at->greaterThan($old));
    }

    public function test_rop_dashboard_builder_counts_conversion(): void
    {
        $rop = User::factory()->rop()->create();
        $employee = User::factory()->employee($rop)->create();

        Project::query()->create([
            'user_id' => $employee->id,
            'company_name' => 'A',
            'status' => Project::STATUS_DRAFT,
            'status_changed_at' => now(),
        ]);
        Project::query()->create([
            'user_id' => $employee->id,
            'company_name' => 'B',
            'status' => Project::STATUS_DRAFT,
            'status_changed_at' => now(),
        ]);
        Project::query()->create([
            'user_id' => $employee->id,
            'company_name' => 'C',
            'status' => Project::STATUS_IN_IMPLEMENTATION,
            'status_changed_at' => now(),
        ]);

        $dashboard = RopTeamDashboard::forRop($rop);

        $this->assertSame(2, $dashboard['funnel'][0]['count']);
        $this->assertSame(1, $dashboard['funnel'][3]['count']);
        $this->assertSame(33.3, $dashboard['conversion_to_implementation_pct']);
    }
}

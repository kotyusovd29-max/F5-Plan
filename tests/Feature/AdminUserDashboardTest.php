<?php

namespace Tests\Feature;

use App\Models\AdminDashboardSnapshot;
use App\Models\AdminDashboardUserStat;
use App\Models\Project;
use App\Models\ProjectAiMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_non_admin_cannot_open_dashboard(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));

        $employee = User::factory()->employee()->create();
        $rop = User::factory()->rop()->create();

        $this->actingAs($employee)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($rop)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($employee)->post(route('admin.dashboard.refresh'))->assertForbidden();
    }

    public function test_admin_captures_team_metrics_into_snapshot_tables(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Админ',
            'online_seconds_total' => 3600,
            'online_seconds_today' => 600,
            'online_day' => now()->toDateString(),
            'last_seen_at' => now(),
        ]);
        $rop = User::factory()->rop()->create(['name' => 'РОП']);
        $employee = User::factory()->employee($rop)->create([
            'name' => 'Сотрудник',
            'online_seconds_total' => 120,
            'online_seconds_today' => 60,
            'online_day' => now()->toDateString(),
        ]);
        User::factory()->employee()->create(['name' => 'Без руководителя']);

        Project::query()->create([
            'user_id' => $employee->id,
            'company_name' => 'Живой план',
            'status' => Project::STATUS_DRAFT,
            'status_changed_at' => now()->subDays(10),
        ]);
        Project::query()->create([
            'user_id' => $employee->id,
            'company_name' => 'Учебный',
            'status' => Project::STATUS_DRAFT,
            'is_training' => true,
        ]);

        $shared = Project::query()->create([
            'user_id' => $rop->id,
            'company_name' => 'Чужой план',
            'status' => Project::STATUS_APPROVED,
        ]);
        $shared->editors()->attach($employee->id);

        ProjectAiMessage::query()->create([
            'project_id' => $shared->id,
            'user_id' => $employee->id,
            'role' => ProjectAiMessage::ROLE_USER,
            'thread' => ProjectAiMessage::THREAD_PROJECT,
            'content' => 'Собери план',
            'status' => ProjectAiMessage::STATUS_COMPLETED,
        ]);
        ProjectAiMessage::query()->create([
            'project_id' => $shared->id,
            'user_id' => $employee->id,
            'role' => ProjectAiMessage::ROLE_ASSISTANT,
            'thread' => ProjectAiMessage::THREAD_MEETING,
            'content' => 'Готово',
            'status' => ProjectAiMessage::STATUS_COMPLETED,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard/Index')
                ->where('dashboard.summary.users_total', 4)
                ->where('dashboard.summary.employees_without_rop', 1)
                ->where('dashboard.summary.online_now', 1)
                ->where('dashboard.summary.projects_draft', 1)
                ->where('dashboard.summary.projects_overdue', 1)
                ->where('dashboard.summary.ai_requests', 1)
                ->where('dashboard.summary.ai_responses', 1)
                ->has('dashboard.users', 4)
                ->has('dashboard.departments', 2)
            );

        $this->assertSame(1, AdminDashboardSnapshot::query()->count());
        $this->assertSame(4, AdminDashboardUserStat::query()->count());

        $stored = AdminDashboardUserStat::query()->where('user_id', $employee->id)->first();
        $this->assertNotNull($stored);
        $this->assertSame(1, $stored->projects_draft);
        $this->assertSame(1, $stored->projects_overdue);
        $this->assertSame(1, $stored->editor_projects);
        $this->assertSame(1, $stored->ai_requests);
        $this->assertSame(1, $stored->ai_responses);
        $this->assertSame('Встреча', $stored->last_ai_thread_label);
        $this->assertSame('Чужой план', $stored->last_ai_project);

        $this->actingAs($admin)
            ->post(route('admin.dashboard.refresh'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame(1, AdminDashboardSnapshot::query()->count());
    }
}

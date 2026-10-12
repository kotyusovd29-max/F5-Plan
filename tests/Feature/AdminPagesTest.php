<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_users(): void
    {
        $this->get(route('admin.users.index'))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_admin_users(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_admin_sees_users_index(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users/Index')
            );
    }

    public function test_admin_can_create_employee_with_rop(): void
    {
        $admin = User::factory()->admin()->create();
        $rop = User::factory()->rop()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Новый сотрудник',
                'email' => 'new@f5plan.local',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => User::ROLE_EMPLOYEE,
                'rop_id' => $rop->id,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'new@f5plan.local',
            'is_admin' => false,
            'role' => User::ROLE_EMPLOYEE,
            'rop_id' => $rop->id,
        ]);
    }

    public function test_employee_requires_rop(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Без РОПа',
                'email' => 'norop@f5plan.local',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => User::ROLE_EMPLOYEE,
            ])
            ->assertSessionHasErrors('rop_id');
    }

    public function test_admin_can_create_rop(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Руководитель',
                'email' => 'rop@f5plan.local',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => User::ROLE_ROP,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'rop@f5plan.local',
            'role' => User::ROLE_ROP,
            'is_admin' => false,
            'rop_id' => null,
        ]);
    }

    public function test_cannot_demote_rop_with_employees(): void
    {
        $admin = User::factory()->admin()->create();
        $rop = User::factory()->rop()->create();
        $otherRop = User::factory()->rop()->create();
        User::factory()->employee($rop)->create();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $rop), [
                'name' => $rop->name,
                'email' => $rop->email,
                'role' => User::ROLE_EMPLOYEE,
                'rop_id' => $otherRop->id,
            ])
            ->assertSessionHasErrors('role');
    }

    public function test_rop_sees_team_projects(): void
    {
        $rop = User::factory()->rop()->create();
        $employee = User::factory()->employee($rop)->create();
        $project = Project::query()->create([
            'user_id' => $employee->id,
            'company_name' => 'Команда ООО',
            'employees_count' => 5,
            'client_tasks' => 'Внедрение',
        ]);

        $this->actingAs($rop)
            ->get(route('home', ['view' => 'team']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home/Index')
                ->where('view', 'team')
                ->where('canViewTeam', true)
                ->has('projects', 1)
                ->where('projects.0.id', $project->id)
            );
    }
}

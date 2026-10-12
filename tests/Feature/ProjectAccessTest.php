<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAccessTest extends TestCase
{
    use RefreshDatabase;

    private function createProjectFor(User $owner): Project
    {
        return Project::query()->create([
            'user_id' => $owner->id,
            'company_name' => 'Тестовая компания',
            'employees_count' => 10,
            'client_tasks' => 'Внедрение',
        ]);
    }

    public function test_any_authenticated_user_can_view_project(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $project = $this->createProjectFor($owner);

        $this->actingAs($viewer)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Show')
                ->where('project.can_edit', false)
            );
    }

    public function test_owner_can_update_project(): void
    {
        $owner = User::factory()->create();
        $project = $this->createProjectFor($owner);

        $this->actingAs($owner)
            ->put(route('projects.update', $project), [
                'company_name' => 'Новое название',
                'website' => '',
                'business_sphere' => '',
                'current_services' => '',
                'employees_count' => 12,
                'client_tasks' => 'Внедрение',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'company_name' => 'Новое название',
        ]);
    }

    public function test_owner_rop_can_update_project(): void
    {
        $rop = User::factory()->rop()->create();
        $owner = User::factory()->employee($rop)->create();
        $project = $this->createProjectFor($owner);

        $this->actingAs($rop)
            ->put(route('projects.update', $project), [
                'company_name' => 'От РОПа',
                'website' => '',
                'business_sphere' => '',
                'current_services' => '',
                'employees_count' => 10,
                'client_tasks' => 'Внедрение',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'company_name' => 'От РОПа',
        ]);
    }

    public function test_assigned_editor_can_update_project(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $project = $this->createProjectFor($owner);
        $project->editors()->attach($editor->id);

        $this->actingAs($editor)
            ->put(route('projects.update', $project), [
                'company_name' => 'От редактора',
                'website' => '',
                'business_sphere' => '',
                'current_services' => '',
                'employees_count' => 10,
                'client_tasks' => 'Внедрение',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'company_name' => 'От редактора',
        ]);
    }

    public function test_outsider_cannot_update_project(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $project = $this->createProjectFor($owner);

        $this->actingAs($outsider)
            ->put(route('projects.update', $project), [
                'company_name' => 'Хак',
                'website' => '',
                'business_sphere' => '',
                'current_services' => '',
                'employees_count' => 10,
                'client_tasks' => 'Внедрение',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'company_name' => 'Тестовая компания',
        ]);
    }

    public function test_owner_can_sync_editors(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $project = $this->createProjectFor($owner);

        $this->actingAs($owner)
            ->put(route('projects.editors.update', $project), [
                'user_ids' => [$editor->id],
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_editors', [
            'project_id' => $project->id,
            'user_id' => $editor->id,
        ]);
    }

    public function test_editor_cannot_manage_editor_list(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $other = User::factory()->create();
        $project = $this->createProjectFor($owner);
        $project->editors()->attach($editor->id);

        $this->actingAs($editor)
            ->put(route('projects.editors.update', $project), [
                'user_ids' => [$other->id],
            ])
            ->assertForbidden();
    }

    public function test_owner_can_delete_project(): void
    {
        $owner = User::factory()->create();
        $project = $this->createProjectFor($owner);

        $this->actingAs($owner)
            ->delete(route('projects.destroy', $project))
            ->assertRedirect(route('home'));

        $this->assertSoftDeleted('projects', [
            'id' => $project->id,
        ]);
    }

    public function test_rop_cannot_delete_project(): void
    {
        $rop = User::factory()->rop()->create();
        $owner = User::factory()->employee($rop)->create();
        $project = $this->createProjectFor($owner);

        $this->actingAs($rop)
            ->delete(route('projects.destroy', $project))
            ->assertForbidden();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_can_delete_any_project(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $project = $this->createProjectFor($owner);

        $this->actingAs($admin)
            ->delete(route('projects.destroy', $project))
            ->assertRedirect(route('home'));

        $this->assertSoftDeleted('projects', [
            'id' => $project->id,
        ]);
    }

    public function test_editor_sees_shared_projects_on_home(): void
    {
        $owner = User::factory()->create(['name' => 'Менеджер Иван']);
        $editor = User::factory()->create();
        $project = $this->createProjectFor($owner);
        $project->editors()->attach($editor->id);

        $this->actingAs($editor)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home/Index')
                ->where('sharedProjectsCount', 1)
                ->has('sharedProjects', 1)
                ->where('sharedProjects.0.id', $project->id)
                ->where('sharedProjects.0.manager_name', 'Менеджер Иван')
            );

        $this->actingAs($editor)
            ->get(route('home', ['view' => 'shared']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home/Index')
                ->where('view', 'shared')
                ->has('sharedProjects', 1)
            );
    }
}

<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function createProjectFor(User $user, array $attributes = []): Project
    {
        return Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
            'status' => Project::STATUS_DRAFT,
            ...$attributes,
        ]);
    }

    public function test_new_project_gets_draft_status(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'company_name' => 'Новая компания',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'company_name' => 'Новая компания',
            'status' => Project::STATUS_DRAFT,
        ]);
    }

    public function test_editor_can_update_project_status(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $project = $this->createProjectFor($owner);
        $project->editors()->attach($editor->id);

        $this->actingAs($editor)
            ->put(route('projects.status.update', $project), [
                'status' => Project::STATUS_PENDING_APPROVAL,
            ])
            ->assertRedirect();

        $this->assertSame(Project::STATUS_PENDING_APPROVAL, $project->fresh()->status);
    }

    public function test_outsider_cannot_update_project_status(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = $this->createProjectFor($owner);

        $this->actingAs($other)
            ->put(route('projects.status.update', $project), [
                'status' => Project::STATUS_APPROVED,
            ])
            ->assertForbidden();
    }

    public function test_home_hides_archived_by_default(): void
    {
        $user = User::factory()->create();
        $active = $this->createProjectFor($user, ['company_name' => 'Активный']);
        $archived = $this->createProjectFor($user, [
            'company_name' => 'Архивный',
            'status' => Project::STATUS_ARCHIVED,
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home/Index')
                ->has('projects', 1)
                ->where('projects.0.id', $active->id)
                ->where('filters.status', 'active')
                ->where('dashboard.drafts_count', 1)
            );

        $this->actingAs($user)
            ->get(route('home', ['status' => 'archived']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('projects', 1)
                ->where('projects.0.id', $archived->id)
                ->where('filters.status', 'archived')
            );
    }

    public function test_home_search_and_sphere_filter(): void
    {
        $user = User::factory()->create();
        $match = $this->createProjectFor($user, [
            'company_name' => 'Альфа Логистика',
            'business_sphere' => 'Логистика',
        ]);
        $this->createProjectFor($user, [
            'company_name' => 'Бета Торг',
            'business_sphere' => 'Ритейл',
        ]);

        $this->actingAs($user)
            ->get(route('home', ['q' => 'Альфа']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('projects', 1)
                ->where('projects.0.id', $match->id)
            );

        $this->actingAs($user)
            ->get(route('home', ['sphere' => 'Ритейл']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('projects', 1)
                ->where('projects.0.company_name', 'Бета Торг')
            );
    }

    public function test_dashboard_counts_pending_approval(): void
    {
        $user = User::factory()->create();
        $this->createProjectFor($user, ['status' => Project::STATUS_DRAFT]);
        $this->createProjectFor($user, ['status' => Project::STATUS_PENDING_APPROVAL]);
        $this->createProjectFor($user, ['status' => Project::STATUS_PENDING_APPROVAL]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboard.drafts_count', 1)
                ->where('dashboard.pending_approval_count', 2)
                ->has('dashboard.recent_ai')
            );
    }

    public function test_soft_delete_and_restore_project(): void
    {
        $owner = User::factory()->create();
        $project = $this->createProjectFor($owner);

        $this->actingAs($owner)
            ->delete(route('projects.destroy', $project))
            ->assertRedirect(route('home'));

        $this->assertSoftDeleted('projects', ['id' => $project->id]);

        $this->actingAs($owner)
            ->get(route('home', ['status' => 'deleted']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('projects', 1)
                ->where('projects.0.id', $project->id)
                ->where('projects.0.is_deleted', true)
                ->where('filters.status', 'deleted')
            );

        $this->actingAs($owner)
            ->post(route('projects.restore', $project))
            ->assertRedirect(route('projects.show', $project));

        $this->assertNull($project->fresh()->deleted_at);
        $this->assertSame(Project::STATUS_DRAFT, $project->fresh()->status);
    }

    public function test_soft_deleted_project_is_hidden_from_default_home(): void
    {
        $user = User::factory()->create();
        $project = $this->createProjectFor($user);
        $project->delete();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('projects', 0));
    }
}

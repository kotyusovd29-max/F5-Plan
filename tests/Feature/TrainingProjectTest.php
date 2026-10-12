<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\TrainingProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_ensure_creates_shared_training_project(): void
    {
        $admin = User::factory()->admin()->create();

        $project = app(TrainingProjectService::class)->ensure();

        $this->assertTrue($project->is_training);
        $this->assertSame(TrainingProjectService::COMPANY_NAME, $project->company_name);
        $this->assertSame($admin->id, $project->user_id);
        $this->assertGreaterThan(0, $project->funnels()->count());
        $this->assertGreaterThan(0, $project->skkFields()->count());
    }

    public function test_any_user_can_view_training_project_but_only_admin_can_edit(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();

        $project = app(TrainingProjectService::class)->ensure();

        $this->actingAs($employee)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Show')
                ->where('project.is_training', true)
                ->where('project.can_edit', false)
            );

        $this->actingAs($employee)
            ->put(route('projects.update', $project), [
                'company_name' => 'Хак',
                'website' => '',
                'business_sphere' => '',
                'current_services' => '',
                'employees_count' => 12,
                'client_tasks' => 'Внедрение',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('project.can_edit', true)
            );
    }

    public function test_training_project_is_hidden_from_home_lists(): void
    {
        $admin = User::factory()->admin()->create();
        app(TrainingProjectService::class)->ensure();

        Project::query()->create([
            'user_id' => $admin->id,
            'company_name' => 'Обычный проект',
        ]);

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('projects', fn ($projects) => collect($projects)->every(
                    fn ($item) => $item['company_name'] !== TrainingProjectService::COMPANY_NAME
                ))
                ->where('training.company_name', TrainingProjectService::COMPANY_NAME)
            );
    }

    public function test_knowledge_base_shares_training_payload(): void
    {
        User::factory()->admin()->create();
        app(TrainingProjectService::class)->ensure();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('knowledge.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('KnowledgeBase/Index')
                ->where('training.company_name', TrainingProjectService::COMPANY_NAME)
                ->has('training.start_url')
            );
    }
}

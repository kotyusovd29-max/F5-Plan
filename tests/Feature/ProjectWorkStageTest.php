<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectWorkStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectWorkStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_user_can_create_work_stage(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->actingAs($user)
            ->post(route('projects.work-stages.store', $project), [
                'name' => 'Аналитика',
                'composition' => "Интервью\nСхема процессов",
                'cost' => 50000,
                'duration_days' => 7,
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_work_stages', [
            'project_id' => $project->id,
            'name' => 'Аналитика',
            'composition' => "Интервью\nСхема процессов",
            'cost' => 50000,
            'duration_days' => 7,
            'sort_order' => 1,
        ]);
    }

    public function test_user_can_update_work_stage(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $stage = ProjectWorkStage::create([
            'project_id' => $project->id,
            'name' => 'Старт',
            'composition' => 'Kick-off',
            'cost' => 10000,
            'duration_days' => 2,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->put(route('projects.work-stages.update', [$project, $stage]), [
                'name' => 'Внедрение',
                'composition' => 'Настройка CRM',
                'cost' => 80000,
                'duration_days' => 14,
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_work_stages', [
            'id' => $stage->id,
            'name' => 'Внедрение',
            'composition' => 'Настройка CRM',
            'cost' => 80000,
            'duration_days' => 14,
        ]);
    }

    public function test_user_can_delete_work_stage(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $stage = ProjectWorkStage::create([
            'project_id' => $project->id,
            'name' => 'Лишний',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->delete(route('projects.work-stages.destroy', [$project, $stage]))
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseMissing('project_work_stages', [
            'id' => $stage->id,
        ]);
    }

    public function test_user_can_delete_all_work_stages(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        ProjectWorkStage::create([
            'project_id' => $project->id,
            'name' => 'Этап 1',
            'sort_order' => 1,
        ]);

        ProjectWorkStage::create([
            'project_id' => $project->id,
            'name' => 'Этап 2',
            'sort_order' => 2,
        ]);

        $this->actingAs($user)
            ->delete(route('projects.work-stages.bulk-destroy', $project))
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseMissing('project_work_stages', [
            'project_id' => $project->id,
        ]);
    }

    public function test_user_can_reorder_work_stages(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $first = ProjectWorkStage::create([
            'project_id' => $project->id,
            'name' => 'Первый',
            'sort_order' => 1,
        ]);

        $second = ProjectWorkStage::create([
            'project_id' => $project->id,
            'name' => 'Второй',
            'sort_order' => 2,
        ]);

        $this->actingAs($user)
            ->putJson(route('projects.work-stages.reorder', $project), [
                'order' => [$second->id, $first->id],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
    }
}

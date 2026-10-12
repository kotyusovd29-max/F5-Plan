<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\ProjectIntegration;
use App\Models\ProjectSkkField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectSectionBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_user_can_delete_all_fields_in_skk_section(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        ProjectSkkField::create([
            'project_id' => $project->id,
            'entity_type' => 'contacts',
            'name' => 'Телефон',
            'sort_order' => 1,
        ]);

        ProjectSkkField::create([
            'project_id' => $project->id,
            'entity_type' => 'deals',
            'name' => 'Бюджет',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->delete(route('projects.skk-fields.sections.destroy', [$project, 'contacts']))
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseMissing('project_skk_fields', [
            'project_id' => $project->id,
            'entity_type' => 'contacts',
        ]);

        $this->assertDatabaseHas('project_skk_fields', [
            'project_id' => $project->id,
            'entity_type' => 'deals',
        ]);
    }

    public function test_user_can_delete_all_integrations(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        ProjectIntegration::create([
            'project_id' => $project->id,
            'name' => 'Chatter',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->delete(route('projects.integrations.bulk-destroy', $project))
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseMissing('project_integrations', [
            'project_id' => $project->id,
        ]);
    }

    public function test_user_can_delete_all_funnels(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Продажи',
            'sort_order' => 1,
        ]);

        FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => 'Новый лид',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->delete(route('projects.funnels.bulk-destroy', $project))
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseMissing('funnels', [
            'project_id' => $project->id,
        ]);
    }
}

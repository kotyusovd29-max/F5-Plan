<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\ProjectSkkField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectSkkFieldStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_structured_skk_field(): void
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

        $stage = FunnelStage::query()->create([
            'funnel_id' => $funnel->id,
            'name' => 'Новая заявка',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('projects.skk-fields.store', $project), [
                'entity_type' => 'deals',
                'name' => 'Источник',
                'value_type' => 'select',
                'is_required' => true,
                'show_in_card' => true,
                'show_in_list' => true,
                'show_in_filter' => false,
                'options' => ['Сайт', 'Звонок'],
                'funnel_stage_id' => $stage->id,
                'comment' => 'Откуда пришёл лид',
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_skk_fields', [
            'project_id' => $project->id,
            'entity_type' => 'deals',
            'name' => 'Источник',
            'value_type' => 'select',
            'is_required' => true,
            'show_in_list' => true,
            'funnel_stage_id' => $stage->id,
            'comment' => 'Откуда пришёл лид',
        ]);

        $field = ProjectSkkField::query()->first();
        $this->assertSame(['Сайт', 'Звонок'], $field->options);
    }

    public function test_select_field_requires_options(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->actingAs($user)
            ->from(route('projects.show', $project))
            ->post(route('projects.skk-fields.store', $project), [
                'entity_type' => 'deals',
                'name' => 'Статус',
                'value_type' => 'select',
            ])
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasErrors('options');
    }

    public function test_project_show_includes_structured_skk_field_props(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        ProjectSkkField::query()->create([
            'project_id' => $project->id,
            'entity_type' => 'deals',
            'name' => 'Бюджет',
            'value_type' => 'number',
            'is_required' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Show')
                ->has('funnelStages')
                ->where('skkSections.0.fields.0.name', 'Бюджет')
                ->where('skkSections.0.fields.0.value_type', 'number')
                ->where('skkSections.0.fields.0.is_required', true)
            );
    }
}

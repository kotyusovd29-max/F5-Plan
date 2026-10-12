<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\LibraryFunnel;
use App\Models\LibrarySkkField;
use App\Models\Project;
use App\Models\ProjectSkkField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_library_funnel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.library.funnels.store'), [
                'name' => 'Типовые продажи',
                'description' => 'Базовая воронка',
                'sort_order' => 0,
                'stages' => [
                    ['name' => 'Новый', 'action' => 'Позвонить', 'comment' => '', 'color' => '#99ccff'],
                    ['name' => 'Успешно', 'action' => '', 'comment' => '', 'color' => '#99ff99'],
                ],
            ])
            ->assertRedirect(route('admin.library.funnels.index'));

        $funnel = LibraryFunnel::query()->where('name', 'Типовые продажи')->first();
        $this->assertNotNull($funnel);
        $this->assertCount(2, $funnel->stages);
    }

    public function test_employee_cannot_manage_library(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.library.funnels.index'))
            ->assertForbidden();
    }

    public function test_consultant_can_apply_library_funnel(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $template = LibraryFunnel::query()->create([
            'name' => 'Продажи B2B',
            'description' => null,
            'sort_order' => 0,
        ]);
        $template->stages()->createMany([
            ['name' => 'Лид', 'action' => null, 'comment' => null, 'color' => '#99ccff', 'sort_order' => 1],
            ['name' => 'Сделка', 'action' => null, 'comment' => null, 'color' => '#ffff99', 'sort_order' => 2],
        ]);

        $this->actingAs($user)
            ->post(route('projects.library.funnels.apply', [$project, $template]))
            ->assertRedirect(route('projects.show', $project));

        $funnel = Funnel::query()->where('project_id', $project->id)->where('name', 'Продажи B2B')->first();
        $this->assertNotNull($funnel);
        $this->assertCount(2, $funnel->stages);
    }

    public function test_consultant_can_apply_library_skk_field_with_stage_match(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $funnel = $project->funnels()->create([
            'name' => 'Основная',
            'sort_order' => 1,
        ]);
        $stage = $funnel->stages()->create([
            'name' => 'Квалификация',
            'sort_order' => 1,
            'color' => '#ffff99',
        ]);

        $template = LibrarySkkField::query()->create([
            'entity_type' => ProjectSkkField::TYPE_DEALS,
            'name' => 'Источник',
            'value_type' => ProjectSkkField::VALUE_SELECT,
            'is_required' => false,
            'show_in_card' => true,
            'show_in_list' => false,
            'show_in_filter' => true,
            'options' => ['Сайт', 'Реклама'],
            'stage_name' => 'Квалификация',
            'comment' => null,
            'sort_order' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('projects.library.skk-fields.apply', [$project, $template]))
            ->assertRedirect(route('projects.show', $project));

        $field = ProjectSkkField::query()
            ->where('project_id', $project->id)
            ->where('name', 'Источник')
            ->first();

        $this->assertNotNull($field);
        $this->assertSame($stage->id, $field->funnel_stage_id);
        $this->assertSame(['Сайт', 'Реклама'], $field->options);
    }

    public function test_admin_can_create_library_skk_field(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.library.skk-fields.store'), [
                'entity_type' => 'contacts',
                'name' => 'Должность',
                'value_type' => 'text',
                'is_required' => false,
                'show_in_card' => true,
                'show_in_list' => true,
                'show_in_filter' => false,
                'stage_name' => null,
                'comment' => null,
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.library.skk-fields.index'));

        $this->assertDatabaseHas('library_skk_fields', [
            'name' => 'Должность',
            'entity_type' => 'contacts',
        ]);
    }
}

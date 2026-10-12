<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\Project;
use App\Models\ProjectIntegration;
use App\Models\ProjectSkkField;
use App\Models\ProjectWorkStage;
use App\Models\StarterPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StarterPackagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_starter_package(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.starter-packages.store'), [
                'name' => 'B2B старт',
                'business_sphere' => 'B2B-услуги',
                'description' => 'Базовый набор',
                'sort_order' => 1,
                'is_active' => true,
                'funnels' => [
                    [
                        'name' => 'Продажи',
                        'description' => null,
                        'stages' => [
                            ['name' => 'Лид', 'action' => 'Позвонить', 'comment' => '', 'color' => '#99ccff'],
                            ['name' => 'Успешно', 'action' => '', 'comment' => '', 'color' => '#99ff99'],
                        ],
                    ],
                ],
                'skk_fields' => [
                    [
                        'entity_type' => 'deals',
                        'name' => 'Источник',
                        'value_type' => 'select',
                        'is_required' => true,
                        'show_in_card' => true,
                        'show_in_list' => false,
                        'show_in_filter' => true,
                        'options' => ['Сайт', 'Реклама'],
                        'funnel_name' => 'Продажи',
                        'stage_name' => 'Лид',
                        'comment' => null,
                    ],
                ],
                'integrations' => [
                    [
                        'widget_slug' => null,
                        'name' => 'Телефония',
                        'comment' => 'Базовая',
                        'billing_quantity' => null,
                    ],
                ],
                'work_stages' => [
                    [
                        'name' => 'Настройка',
                        'composition' => 'Базовая настройка',
                        'cost' => 50000,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.starter-packages.index'));

        $package = StarterPackage::query()->where('name', 'B2B старт')->first();
        $this->assertNotNull($package);
        $this->assertSame('B2B-услуги', $package->business_sphere);
        $this->assertCount(1, $package->funnels);
        $this->assertCount(2, $package->funnels->first()->stages);
        $this->assertCount(1, $package->skkFields);
        $this->assertCount(1, $package->integrations);
        $this->assertCount(1, $package->workStages);
    }

    public function test_employee_cannot_manage_starter_packages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.starter-packages.index'))
            ->assertForbidden();
    }

    public function test_create_page_includes_starter_packages(): void
    {
        $user = User::factory()->create();
        StarterPackage::query()->create([
            'name' => 'E-com',
            'business_sphere' => 'e-commerce',
            'description' => null,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        StarterPackage::query()->create([
            'name' => 'Скрытый',
            'business_sphere' => 'other',
            'description' => null,
            'sort_order' => 1,
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->get(route('projects.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Create')
                ->has('starterPackages', 1)
                ->where('starterPackages.0.name', 'E-com')
            );
    }

    public function test_user_can_create_project_from_starter_package(): void
    {
        $user = User::factory()->create();
        $package = StarterPackage::query()->create([
            'name' => 'Логистика старт',
            'business_sphere' => 'Логистика',
            'description' => 'Набор для логистики',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $funnel = $package->funnels()->create([
            'name' => 'Заявки',
            'description' => null,
            'sort_order' => 1,
        ]);
        $funnel->stages()->createMany([
            ['name' => 'Новая', 'action' => null, 'comment' => null, 'color' => '#99ccff', 'sort_order' => 1],
            ['name' => 'В работе', 'action' => null, 'comment' => null, 'color' => '#ffff99', 'sort_order' => 2],
        ]);

        $package->skkFields()->create([
            'entity_type' => ProjectSkkField::TYPE_DEALS,
            'name' => 'Город',
            'value_type' => ProjectSkkField::VALUE_TEXT,
            'is_required' => false,
            'show_in_card' => true,
            'show_in_list' => false,
            'show_in_filter' => false,
            'options' => null,
            'funnel_name' => 'Заявки',
            'stage_name' => 'Новая',
            'comment' => null,
            'sort_order' => 1,
        ]);

        $package->integrations()->create([
            'name' => 'WhatsApp',
            'widget_slug' => null,
            'widget_url' => null,
            'comment' => 'Чат',
            'billing_quantity' => null,
            'sort_order' => 1,
        ]);

        $package->workStages()->create([
            'name' => 'Аудит',
            'composition' => 'Разбор процессов',
            'cost' => 15000,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'company_name' => 'ООО Логист',
                'business_sphere' => '',
                'starter_package_id' => $package->id,
            ])
            ->assertRedirect();

        $project = Project::query()->where('company_name', 'ООО Логист')->first();
        $this->assertNotNull($project);
        $this->assertSame('Логистика', $project->business_sphere);

        $createdFunnel = Funnel::query()->where('project_id', $project->id)->where('name', 'Заявки')->first();
        $this->assertNotNull($createdFunnel);
        $this->assertCount(2, $createdFunnel->stages);

        $field = ProjectSkkField::query()->where('project_id', $project->id)->where('name', 'Город')->first();
        $this->assertNotNull($field);
        $this->assertSame($createdFunnel->stages->firstWhere('name', 'Новая')?->id, $field->funnel_stage_id);

        $this->assertTrue(
            ProjectIntegration::query()->where('project_id', $project->id)->where('name', 'WhatsApp')->exists()
        );
        $this->assertTrue(
            ProjectWorkStage::query()->where('project_id', $project->id)->where('name', 'Аудит')->exists()
        );
    }

    public function test_user_can_create_project_without_starter_package(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'company_name' => 'Пустой проект',
                'starter_package_id' => null,
            ])
            ->assertRedirect();

        $project = Project::query()->where('company_name', 'Пустой проект')->first();
        $this->assertNotNull($project);
        $this->assertCount(0, $project->funnels);
        $this->assertCount(0, $project->skkFields);
        $this->assertCount(0, $project->integrations);
        $this->assertCount(0, $project->workStages);
    }
}

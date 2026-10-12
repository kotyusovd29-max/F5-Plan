<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeAllProjectsDepartmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_all_projects_view_groups_employees_by_rop_department(): void
    {
        $admin = User::factory()->admin()->create();
        $ropAlpha = User::factory()->rop()->create(['name' => 'Альфа РОП']);
        $ropBeta = User::factory()->rop()->create(['name' => 'Бета РОП']);
        $employeeAlpha = User::factory()->employee($ropAlpha)->create(['name' => 'Сотрудник Альфа']);
        $employeeBeta = User::factory()->employee($ropBeta)->create(['name' => 'Сотрудник Бета']);

        Project::query()->create([
            'user_id' => $employeeAlpha->id,
            'company_name' => 'Компания А',
        ]);
        Project::query()->create([
            'user_id' => $employeeBeta->id,
            'company_name' => 'Компания Б',
        ]);
        Project::query()->create([
            'user_id' => $ropAlpha->id,
            'company_name' => 'Компания РОП',
        ]);

        $this->actingAs($admin)
            ->get(route('home', ['view' => 'all']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home/Index')
                ->where('view', 'all')
                ->has('departments', 2)
                ->where('departments.0.title', 'Отдел Альфа РОП')
                ->where('departments.1.title', 'Отдел Бета РОП')
                ->has('departments.0.employees', 2)
                ->has('departments.1.employees', 1)
                ->where('employees', [])
            );
    }
}

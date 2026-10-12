<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_project_create(): void
    {
        $this->get(route('projects.create'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_sees_create_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('projects.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Create')
            );
    }

    public function test_user_can_create_project(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'company_name' => 'ООО Тест',
                'employees_count' => 5,
                'client_tasks' => 'Автоматизация продаж',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
            'employees_count' => 5,
        ]);
    }
}

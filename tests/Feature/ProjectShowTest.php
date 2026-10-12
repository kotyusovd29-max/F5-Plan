<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_project_page(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
            'amocrm_deal_url' => 'https://example.amocrm.ru/leads/detail/42',
        ]);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Show')
                ->where('project.company_name', 'ООО Тест')
                ->where('project.amocrm_deal_url', 'https://example.amocrm.ru/leads/detail/42')
                ->has('amocrmLicenses.tariffs', 3)
                ->where('amocrmLicenses.tariffs.0.name', 'Базовый')
                ->where('amocrmLicenses.tariffs.1.name', 'Расширенный')
                ->where('amocrmLicenses.tariffs.2.name', 'Профессиональный')
                ->where('project.amocrm_license_tariff', null)
            );
    }

    public function test_user_can_select_amocrm_license_tariff(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
            'employees_count' => 8,
        ]);

        $this->actingAs($user)
            ->put(route('projects.amocrm-license.update', $project), [
                'amocrm_license_tariff' => 'professional',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'amocrm_license_tariff' => 'professional',
        ]);
    }

    public function test_amocrm_deal_url_can_be_updated_and_stays_out_of_ai_context(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
            'client_tasks' => 'Внедрить CRM',
        ]);

        $this->actingAs($user)
            ->put(route('projects.update', $project), [
                'company_name' => 'ООО Тест',
                'website' => '',
                'business_sphere' => '',
                'current_services' => '',
                'employees_count' => '',
                'client_tasks' => 'Внедрить CRM',
                'amocrm_deal_url' => 'https://example.amocrm.ru/leads/detail/99',
            ])
            ->assertRedirect();

        $project->refresh();
        $this->assertSame('https://example.amocrm.ru/leads/detail/99', $project->amocrm_deal_url);

        $context = app(\App\Services\ProjectAiContextBuilder::class)->build($project);
        $encoded = json_encode($context, JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('amocrm_deal_url', $encoded);
        $this->assertStringNotContainsString('leads/detail/99', $encoded);
    }

    public function test_adding_skk_field_returns_inertia_redirect_not_json(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('projects.skk-fields.store', $project), [
                'form_type' => 'new_skk_field',
                'entity_type' => 'deals',
                'name' => 'Бюджет',
                'comment' => 'Числовое поле',
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_skk_fields', [
            'project_id' => $project->id,
            'entity_type' => 'deals',
            'name' => 'Бюджет',
        ]);
    }
}

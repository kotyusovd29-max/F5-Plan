<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Support\F5WidgetCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectIntegrationWidgetCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_catalog_widget_by_slug(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $widget = collect(F5WidgetCatalog::widgets())->first(
            fn (array $item): bool => ($item['slug'] ?? '') === 'telegram-amocrm'
        );

        $this->assertNotNull($widget);

        $this->actingAs($user)
            ->post(route('projects.integrations.store', $project), [
                'widget_slug' => $widget['slug'],
                'comment' => 'Уведомления менеджерам',
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_integrations', [
            'project_id' => $project->id,
            'widget_slug' => 'telegram-amocrm',
            'name' => $widget['name'],
            'widget_url' => $widget['url'],
            'comment' => 'Уведомления менеджерам',
        ]);
    }

    public function test_user_can_add_custom_integration(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->actingAs($user)
            ->post(route('projects.integrations.store', $project), [
                'widget_slug' => F5WidgetCatalog::CUSTOM_SLUG,
                'name' => 'Телефония Mango',
                'comment' => 'Входящие звонки',
                'payment_type' => 'subscription',
                'cost' => 5000,
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_integrations', [
            'project_id' => $project->id,
            'widget_slug' => null,
            'widget_url' => null,
            'name' => 'Телефония Mango',
            'comment' => 'Входящие звонки',
            'payment_type' => 'subscription',
            'cost' => 5000,
        ]);
    }

    public function test_user_can_add_custom_one_time_integration(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->actingAs($user)
            ->post(route('projects.integrations.store', $project), [
                'widget_slug' => F5WidgetCatalog::CUSTOM_SLUG,
                'name' => 'Внедрение телефонии',
                'payment_type' => 'one_time',
                'cost' => 45000,
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_integrations', [
            'project_id' => $project->id,
            'widget_slug' => null,
            'name' => 'Внедрение телефонии',
            'payment_type' => 'one_time',
            'cost' => 45000,
        ]);
    }

    public function test_custom_one_time_requires_cost(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->actingAs($user)
            ->from(route('projects.show', $project))
            ->post(route('projects.integrations.store', $project), [
                'widget_slug' => F5WidgetCatalog::CUSTOM_SLUG,
                'name' => 'Разовая услуга',
                'payment_type' => 'one_time',
            ])
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasErrors('cost');
    }

    public function test_store_requires_widget_or_name(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->actingAs($user)
            ->from(route('projects.show', $project))
            ->post(route('projects.integrations.store', $project), [
                'widget_slug' => F5WidgetCatalog::CUSTOM_SLUG,
                'comment' => 'Без названия',
            ])
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasErrors('name');
    }

    public function test_normalize_integration_input_resolves_catalog_widget_by_slug(): void
    {
        $resolved = F5WidgetCatalog::normalizeIntegrationInput([
            'widget_slug' => 'telegram-amocrm',
            'comment' => 'Уведомления',
        ]);

        $widget = F5WidgetCatalog::findBySlug('telegram-amocrm');

        $this->assertNotNull($widget);
        $this->assertSame('telegram-amocrm', $resolved['widget_slug']);
        $this->assertSame($widget['name'], $resolved['name']);
        $this->assertSame($widget['url'], $resolved['widget_url']);
        $this->assertSame('Уведомления', $resolved['comment']);
    }

    public function test_normalize_integration_input_resolves_catalog_widget_by_name(): void
    {
        $resolved = F5WidgetCatalog::normalizeIntegrationInput([
            'name' => 'Chatter',
            'comment' => 'Мессенджеры',
        ]);

        $this->assertSame('chatter', $resolved['widget_slug']);
        $this->assertSame('Chatter', $resolved['name']);
        $this->assertNotNull($resolved['widget_url']);
    }

    public function test_normalize_integration_input_keeps_custom_integration(): void
    {
        $resolved = F5WidgetCatalog::normalizeIntegrationInput([
            'name' => 'Телефония Mango',
            'comment' => 'Входящие звонки',
        ]);

        $this->assertNull($resolved['widget_slug']);
        $this->assertNull($resolved['widget_url']);
        $this->assertSame('Телефония Mango', $resolved['name']);
    }

    public function test_for_ai_context_excludes_custom_slug(): void
    {
        $slugs = array_column(F5WidgetCatalog::forAiContext(), 'slug');

        $this->assertNotContains(F5WidgetCatalog::CUSTOM_SLUG, $slugs);
        $this->assertContains('chatter', $slugs);
    }

    public function test_project_show_includes_widget_catalog(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Show')
                ->has('f5WidgetCatalog.widgets')
                ->has('f5WidgetCatalog.categories')
            );
    }
}

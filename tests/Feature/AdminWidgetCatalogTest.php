<?php

namespace Tests\Feature;

use App\Models\F5Widget;
use App\Models\User;
use App\Support\F5WidgetCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWidgetCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_widget(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.widgets.store'), [
                'slug' => 'test-widget',
                'name' => 'Test Widget',
                'description' => 'Описание',
                'price' => '100 ₽',
                'category' => 'utilities',
                'url' => 'https://example.com/widget',
                'is_active' => true,
                'sort_order' => 1,
                'pricing_model' => '',
            ])
            ->assertRedirect(route('admin.widgets.index'));

        $this->assertDatabaseHas('f5_widgets', [
            'slug' => 'test-widget',
            'name' => 'Test Widget',
            'is_active' => true,
        ]);

        $this->assertSame('Test Widget', F5WidgetCatalog::findBySlug('test-widget')['name'] ?? null);
    }

    public function test_employee_cannot_manage_widgets(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.widgets.index'))
            ->assertForbidden();
    }

    public function test_inactive_widget_excluded_from_catalog_slugs(): void
    {
        F5Widget::query()->create([
            'slug' => 'active-one',
            'name' => 'Active',
            'description' => '',
            'price' => '',
            'category' => 'utilities',
            'url' => null,
            'pricing' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        F5Widget::query()->create([
            'slug' => 'hidden-one',
            'name' => 'Hidden',
            'description' => '',
            'price' => '',
            'category' => 'utilities',
            'url' => null,
            'pricing' => null,
            'is_active' => false,
            'sort_order' => 1,
        ]);

        F5WidgetCatalog::forgetCache();

        $slugs = F5WidgetCatalog::slugs();

        $this->assertContains('active-one', $slugs);
        $this->assertNotContains('hidden-one', $slugs);
        $this->assertContains(F5WidgetCatalog::CUSTOM_SLUG, $slugs);
    }

    public function test_catalog_falls_back_to_config_when_table_empty(): void
    {
        $this->assertSame(0, F5Widget::query()->count());
        $this->assertContains('chatter', F5WidgetCatalog::slugs());
    }
}

<?php

namespace Database\Seeders;

use App\Models\F5Widget;
use App\Support\F5WidgetCatalog;
use Illuminate\Database\Seeder;

class F5WidgetSeeder extends Seeder
{
    public function run(): void
    {
        $widgets = config('f5_widgets.widgets', []);
        $sortOrder = 0;

        $icons = config('f5_widget_icons', []);

        foreach ($widgets as $widget) {
            $slug = (string) ($widget['slug'] ?? '');

            if ($slug === '' || $slug === F5WidgetCatalog::CUSTOM_SLUG) {
                continue;
            }

            $icon = filled($widget['icon'] ?? null)
                ? (string) $widget['icon']
                : (filled($icons[$slug] ?? null) ? (string) $icons[$slug] : null);

            F5Widget::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => (string) ($widget['name'] ?? $slug),
                    'description' => (string) ($widget['description'] ?? ''),
                    'price' => (string) ($widget['price'] ?? ''),
                    'category' => (string) ($widget['category'] ?? 'utilities'),
                    'url' => filled($widget['url'] ?? null) ? (string) $widget['url'] : null,
                    'icon' => $icon,
                    'pricing' => is_array($widget['pricing'] ?? null) ? $widget['pricing'] : null,
                    'is_active' => true,
                    'sort_order' => $sortOrder++,
                ]
            );
        }

        F5WidgetCatalog::forgetCache();
    }
}

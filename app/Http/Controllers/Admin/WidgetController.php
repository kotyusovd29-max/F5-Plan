<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWidgetRequest;
use App\Http\Requests\Admin\UpdateWidgetRequest;
use App\Models\F5Widget;
use App\Support\F5WidgetCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WidgetController extends Controller
{
    public function index(Request $request): Response
    {
        $category = (string) $request->query('category', '');
        $q = trim((string) $request->query('q', ''));
        $categories = F5WidgetCatalog::categories();

        $widgets = F5Widget::query()
            ->ordered()
            ->when($category !== '' && isset($categories[$category]), fn ($query) => $query->where('category', $category))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('slug', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->paginate(30)
            ->withQueryString()
            ->through(fn (F5Widget $widget) => $this->serializeWidget($widget, $categories));

        return Inertia::render('Admin/Widgets/Index', [
            'widgets' => $widgets,
            'categories' => $categories,
            'sourceUrl' => F5WidgetCatalog::config()['source_url'] ?? null,
            'filters' => [
                'q' => $q,
                'category' => $category,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Widgets/Create', [
            'categories' => $this->categoryOptions(),
            'sourceUrl' => F5WidgetCatalog::config()['source_url'] ?? null,
        ]);
    }

    public function store(StoreWidgetRequest $request): RedirectResponse
    {
        F5Widget::query()->create($request->widgetPayload());

        return redirect()
            ->route('admin.widgets.index')
            ->with('success', 'Виджет добавлен.');
    }

    public function edit(F5Widget $widget): Response
    {
        return Inertia::render('Admin/Widgets/Edit', [
            'widget' => $this->serializeWidgetForForm($widget),
            'categories' => $this->categoryOptions(),
            'sourceUrl' => F5WidgetCatalog::config()['source_url'] ?? null,
        ]);
    }

    public function update(UpdateWidgetRequest $request, F5Widget $widget): RedirectResponse
    {
        $widget->update($request->widgetPayload());

        return redirect()
            ->route('admin.widgets.index')
            ->with('success', 'Виджет обновлён.');
    }

    public function destroy(F5Widget $widget): RedirectResponse
    {
        $widget->delete();

        return redirect()
            ->route('admin.widgets.index')
            ->with('success', 'Виджет удалён.');
    }

    /**
     * @param  array<string, string>  $categories
     * @return array<string, mixed>
     */
    private function serializeWidget(F5Widget $widget, array $categories): array
    {
        return [
            'id' => $widget->id,
            'slug' => $widget->slug,
            'name' => $widget->name,
            'description' => $widget->description,
            'price' => $widget->price,
            'category' => $widget->category,
            'category_label' => $categories[$widget->category] ?? $widget->category,
            'url' => $widget->url,
            'is_active' => $widget->is_active,
            'sort_order' => $widget->sort_order,
            'edit_url' => route('admin.widgets.edit', $widget),
            'destroy_url' => route('admin.widgets.destroy', $widget),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWidgetForForm(F5Widget $widget): array
    {
        $pricing = is_array($widget->pricing) ? $widget->pricing : [];
        $model = (string) ($pricing['model'] ?? '');
        $packages = is_array($pricing['packages'] ?? null) ? $pricing['packages'] : [];

        return [
            'id' => $widget->id,
            'slug' => $widget->slug,
            'name' => $widget->name,
            'description' => $widget->description ?? '',
            'price' => $widget->price ?? '',
            'category' => $widget->category,
            'url' => $widget->url ?? '',
            'is_active' => $widget->is_active,
            'sort_order' => $widget->sort_order,
            'pricing_model' => $model,
            'pricing_unit_monthly' => $pricing['unit_monthly'] ?? null,
            'pricing_quantity_label' => $pricing['quantity_label'] ?? 'каналов',
            'pricing_default_quantity' => $pricing['default_quantity'] ?? 1,
            'pricing_min_quantity' => $pricing['min_quantity'] ?? 1,
            'pricing_package_6' => $packages[6] ?? $packages['6'] ?? null,
            'pricing_package_12' => $packages[12] ?? $packages['12'] ?? null,
            'update_url' => route('admin.widgets.update', $widget),
            'destroy_url' => route('admin.widgets.destroy', $widget),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function categoryOptions(): array
    {
        return collect(F5WidgetCatalog::categories())
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }
}

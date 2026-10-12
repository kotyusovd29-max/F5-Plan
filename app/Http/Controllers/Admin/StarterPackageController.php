<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStarterPackageRequest;
use App\Http\Requests\Admin\UpdateStarterPackageRequest;
use App\Models\StarterPackage;
use App\Services\StarterPackageWriter;
use App\Support\F5WidgetCatalog;
use App\Support\FunnelStageColors;
use App\Support\StarterPackagePresenter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StarterPackageController extends Controller
{
    public function index(): Response
    {
        $packages = StarterPackage::query()
            ->ordered()
            ->withCount(['funnels', 'skkFields', 'integrations', 'workStages'])
            ->paginate(20)
            ->through(fn (StarterPackage $package) => [
                'id' => $package->id,
                'name' => $package->name,
                'business_sphere' => $package->business_sphere,
                'description' => $package->description,
                'is_active' => $package->is_active,
                'funnels_count' => $package->funnels_count,
                'skk_fields_count' => $package->skk_fields_count,
                'integrations_count' => $package->integrations_count,
                'work_stages_count' => $package->work_stages_count,
                'edit_url' => route('admin.starter-packages.edit', $package),
                'destroy_url' => route('admin.starter-packages.destroy', $package),
            ]);

        return Inertia::render('Admin/StarterPackages/Index', [
            'packages' => $packages,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/StarterPackages/Create', $this->formOptions());
    }

    public function store(StoreStarterPackageRequest $request, StarterPackageWriter $writer): RedirectResponse
    {
        $writer->create($request->packagePayload());

        return redirect()
            ->route('admin.starter-packages.index')
            ->with('success', 'Стартовый пакет создан.');
    }

    public function edit(StarterPackage $starterPackage): Response
    {
        return Inertia::render('Admin/StarterPackages/Edit', [
            'package' => StarterPackagePresenter::forAdminForm($starterPackage),
            ...$this->formOptions(),
        ]);
    }

    public function update(
        UpdateStarterPackageRequest $request,
        StarterPackage $starterPackage,
        StarterPackageWriter $writer
    ): RedirectResponse {
        $writer->update($starterPackage, $request->packagePayload());

        return redirect()
            ->route('admin.starter-packages.index')
            ->with('success', 'Стартовый пакет обновлён.');
    }

    public function destroy(StarterPackage $starterPackage): RedirectResponse
    {
        $starterPackage->delete();

        return redirect()
            ->route('admin.starter-packages.index')
            ->with('success', 'Стартовый пакет удалён.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'colorOptions' => FunnelStageColors::palette(),
            'entityOptions' => StarterPackagePresenter::entityOptions(),
            'valueTypeOptions' => StarterPackagePresenter::valueTypeOptions(),
            'widgetCatalog' => F5WidgetCatalog::forFrontend(),
        ];
    }
}

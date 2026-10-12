<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLibraryFunnelRequest;
use App\Http\Requests\Admin\UpdateLibraryFunnelRequest;
use App\Models\LibraryFunnel;
use App\Support\F5WidgetCatalog;
use App\Support\FunnelStageColors;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LibraryFunnelController extends Controller
{
    public function index(): Response
    {
        $funnels = LibraryFunnel::query()
            ->withCount('stages')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->through(fn (LibraryFunnel $funnel) => [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'description' => $funnel->description,
                'stages_count' => $funnel->stages_count,
                'sort_order' => $funnel->sort_order,
                'edit_url' => route('admin.library.funnels.edit', $funnel),
                'destroy_url' => route('admin.library.funnels.destroy', $funnel),
            ]);

        return Inertia::render('Admin/Library/Funnels/Index', [
            'funnels' => $funnels,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Library/Funnels/Create', [
            'colorOptions' => FunnelStageColors::palette(),
            'f5WidgetCatalog' => F5WidgetCatalog::forFrontend(),
        ]);
    }

    public function store(StoreLibraryFunnelRequest $request): RedirectResponse
    {
        $payload = $request->funnelPayload();

        DB::transaction(function () use ($payload) {
            $funnel = LibraryFunnel::query()->create([
                'name' => $payload['name'],
                'description' => $payload['description'],
                'sort_order' => $payload['sort_order'],
            ]);

            foreach ($payload['stages'] as $stage) {
                $funnel->stages()->create($stage);
            }
        });

        return redirect()
            ->route('admin.library.funnels.index')
            ->with('success', 'Шаблон воронки создан.');
    }

    public function edit(LibraryFunnel $libraryFunnel): Response
    {
        $libraryFunnel->load('stages');

        return Inertia::render('Admin/Library/Funnels/Edit', [
            'funnel' => [
                'id' => $libraryFunnel->id,
                'name' => $libraryFunnel->name,
                'description' => $libraryFunnel->description ?? '',
                'sort_order' => $libraryFunnel->sort_order,
                'stages' => $libraryFunnel->stages->map(fn ($stage) => [
                    'name' => $stage->name,
                    'action' => $stage->action ?? '',
                    'comment' => $stage->comment ?? '',
                    'stage_type' => $stage->stage_type ?? '',
                    'deadline' => $stage->deadline ?? '',
                    'kind' => $stage->kind ?: 'normal',
                    'outcomes' => is_array($stage->outcomes) ? $stage->outcomes : [],
                    'color' => $stage->color ?? FunnelStageColors::defaultForIndex(0),
                ])->values()->all(),
                'update_url' => route('admin.library.funnels.update', $libraryFunnel),
                'destroy_url' => route('admin.library.funnels.destroy', $libraryFunnel),
            ],
            'colorOptions' => FunnelStageColors::palette(),
            'f5WidgetCatalog' => F5WidgetCatalog::forFrontend(),
        ]);
    }

    public function update(UpdateLibraryFunnelRequest $request, LibraryFunnel $libraryFunnel): RedirectResponse
    {
        $payload = $request->funnelPayload();

        DB::transaction(function () use ($libraryFunnel, $payload) {
            $libraryFunnel->update([
                'name' => $payload['name'],
                'description' => $payload['description'],
                'sort_order' => $payload['sort_order'],
            ]);

            $libraryFunnel->stages()->delete();

            foreach ($payload['stages'] as $stage) {
                $libraryFunnel->stages()->create($stage);
            }
        });

        return redirect()
            ->route('admin.library.funnels.index')
            ->with('success', 'Шаблон воронки обновлён.');
    }

    public function destroy(LibraryFunnel $libraryFunnel): RedirectResponse
    {
        $libraryFunnel->delete();

        return redirect()
            ->route('admin.library.funnels.index')
            ->with('success', 'Шаблон воронки удалён.');
    }
}

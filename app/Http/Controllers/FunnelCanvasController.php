<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateFunnelCanvasLayoutRequest;
use App\Models\Funnel;
use App\Models\Project;
use App\Services\FunnelCanvasLayoutService;
use App\Support\F5WidgetCatalog;
use App\Support\FunnelBoardWidgets;
use App\Support\FunnelStageWidgets;
use App\Support\ProjectAccessPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FunnelCanvasController extends Controller
{
    public function show(
        Project $project,
        Funnel $funnel,
        FunnelCanvasLayoutService $layoutService
    ): Response {
        abort_unless($funnel->project_id === $project->id, 404);

        $funnel->load(['stages']);
        $layoutService->ensureStagePositions($funnel);
        $funnel->load(['stages']);

        $project->loadMissing('funnels');

        return Inertia::render('Funnels/Canvas', [
            'project' => [
                'id' => $project->id,
                'company_name' => $project->company_name,
                ...ProjectAccessPresenter::forUser($project, request()->user()),
                'urls' => [
                    'canvas' => route('projects.canvas.show', $project),
                ],
            ],
            'funnels' => $project->funnels->map(fn (Funnel $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'canvas_url' => route('projects.funnels.canvas.show', [$project, $item]),
            ])->values()->all(),
            'funnel' => $this->serializeFunnel($project, $funnel),
            'viewport' => [
                'offset_x' => (int) $funnel->canvas_offset_x,
                'offset_y' => (int) $funnel->canvas_offset_y,
                'zoom' => (float) ($funnel->canvas_zoom ?: 1),
            ],
            'urls' => [
                'save' => route('projects.funnels.canvas.update', [$project, $funnel]),
                'share' => route('projects.funnels.canvas.share', [$project, $funnel]),
            ],
            'shareUrl' => $project->boardShareUrl(),
            'shareSettings' => $project->boardShareSettingsForFrontend(),
            'f5WidgetCatalog' => F5WidgetCatalog::forFrontend(),
        ]);
    }

    public function share(Request $request, Project $project, Funnel $funnel): JsonResponse
    {
        abort_unless($funnel->project_id === $project->id, 404);

        if ($request->boolean('revoke')) {
            $project->revokeBoardShare();

            return response()->json([
                'url' => null,
                ...$project->fresh()->boardShareSettingsForFrontend(),
                'message' => 'Ссылка отозвана',
            ]);
        }

        if ($request->boolean('regenerate')) {
            $project->forceFill(['board_share_token' => null])->save();
            $project->clearLegacyFunnelShareTokens();
        }

        $project->ensureBoardShareToken();

        if ($request->exists('comments_enabled')) {
            $project->forceFill([
                'board_share_comments_enabled' => $request->boolean('comments_enabled'),
            ])->save();
        }

        if ($request->exists('expires_at')) {
            $expiresAt = $request->input('expires_at');
            $project->forceFill([
                'board_share_expires_at' => filled($expiresAt) ? $expiresAt : null,
            ])->save();
        }

        if ($request->boolean('clear_password')) {
            $project->setBoardSharePassword(null);
        } elseif ($request->filled('password')) {
            $project->setBoardSharePassword((string) $request->input('password'));
        }

        $project->refresh();

        return response()->json([
            'url' => $project->boardShareUrl(),
            ...$project->boardShareSettingsForFrontend(),
            'message' => 'Настройки ссылки сохранены',
        ]);
    }

    public function refresh(
        Project $project,
        Funnel $funnel,
        FunnelCanvasLayoutService $layoutService
    ): JsonResponse {
        abort_unless($funnel->project_id === $project->id, 404);

        $funnel->load('stages');
        $layoutService->ensureStagePositions($funnel);
        $funnel->load('stages');

        return response()->json([
            'stages' => $this->serializeStages($project, $funnel),
            'board_widgets' => FunnelBoardWidgets::present(
                is_array($funnel->board_widgets) ? $funnel->board_widgets : []
            ),
            'title' => $funnel->name,
        ]);
    }

    public function update(
        UpdateFunnelCanvasLayoutRequest $request,
        Project $project,
        Funnel $funnel
    ): JsonResponse {
        abort_unless($funnel->project_id === $project->id, 404);

        if ($request->has('stages')) {
            $stageIds = $funnel->stages()->pluck('id');

            foreach ($request->validated('stages') as $stageData) {
                if (! $stageIds->contains($stageData['id'])) {
                    abort(403);
                }

                $funnel->stages()
                    ->where('id', $stageData['id'])
                    ->update([
                        'canvas_pos_x' => $stageData['canvas_pos_x'],
                        'canvas_pos_y' => $stageData['canvas_pos_y'],
                    ]);
            }
        }

        if ($request->has('viewport')) {
            $viewport = $request->validated('viewport');

            $funnel->update([
                'canvas_offset_x' => $viewport['offset_x'],
                'canvas_offset_y' => $viewport['offset_y'],
                'canvas_zoom' => $viewport['zoom'],
            ]);
        }

        if ($request->has('board_widgets')) {
            $stageIds = $funnel->stages()->pluck('id')->map(fn ($id) => (int) $id)->all();

            $funnel->update([
                'board_widgets' => FunnelBoardWidgets::normalize(
                    $request->validated('board_widgets'),
                    $stageIds
                ),
            ]);
        }

        return response()->json([
            'success' => true,
            'board_widgets' => FunnelBoardWidgets::present(
                is_array($funnel->fresh()->board_widgets) ? $funnel->board_widgets : []
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeFunnel(Project $project, Funnel $funnel): array
    {
        return [
            'id' => $funnel->id,
            'name' => $funnel->name,
            'description' => $funnel->description,
            'urls' => [
                'update' => route('projects.funnels.update', [$project, $funnel]),
                'stages_store' => route('projects.funnels.stages.store', [$project, $funnel]),
            ],
            'stages' => $this->serializeStages($project, $funnel),
            'board_widgets' => FunnelBoardWidgets::present(
                is_array($funnel->board_widgets) ? $funnel->board_widgets : []
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeStages(Project $project, Funnel $funnel): array
    {
        return $funnel->stages->values()->map(function ($stage, int $index) use ($project, $funnel) {
            return [
                'id' => $stage->id,
                'number' => $index + 1,
                'name' => $stage->name,
                'action' => $stage->action,
                'comment' => $stage->comment,
                'stage_type' => $stage->stage_type,
                'deadline' => $stage->deadline,
                'kind' => $stage->kind ?: 'normal',
                'outcomes' => is_array($stage->outcomes) ? $stage->outcomes : [],
                'widgets' => FunnelStageWidgets::present(
                    is_array($stage->widgets) ? $stage->widgets : []
                ),
                'sort_order' => $stage->sort_order,
                'canvas_pos_x' => $stage->canvas_pos_x,
                'canvas_pos_y' => $stage->canvas_pos_y,
                'urls' => [
                    'update' => route('projects.funnels.stages.update', [$project, $funnel, $stage]),
                    'destroy' => route('projects.funnels.stages.destroy', [$project, $funnel, $stage]),
                ],
            ];
        })->all();
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCanvasLayoutRequest;
use App\Models\Funnel;
use App\Models\Project;
use App\Models\ProjectCanvasBlock;
use App\Models\ProjectSkkField;
use App\Services\CanvasBlockDeletionService;
use App\Services\CanvasLayoutService;
use App\Support\F5WidgetCatalog;
use App\Support\IntegrationPresenter;
use App\Support\ProjectAccessPresenter;
use App\Support\ProjectAiPrompts;
use App\Support\ProjectAiUrls;
use App\Support\SkkFieldPresenter;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectCanvasController extends Controller
{
    public function show(Project $project, CanvasLayoutService $canvasLayoutService): Response
    {
        $project->load([
            'user',
            'funnels.stages',
            'integrations',
            'skkFields.funnelStage.funnel',
        ]);

        $blocks = $canvasLayoutService->syncBlocks($project);

        return Inertia::render('Projects/Canvas', [
            'project' => $this->serializeProject($project),
            'funnelStages' => SkkFieldPresenter::funnelStageOptions($project),
            'blocks' => $this->serializeBlocks($project, $blocks, $canvasLayoutService),
            'viewport' => [
                'offset_x' => (int) $project->canvas_offset_x,
                'offset_y' => (int) $project->canvas_offset_y,
                'zoom' => (float) ($project->canvas_zoom ?: 1),
            ],
            'f5WidgetCatalog' => F5WidgetCatalog::forFrontend(),
            'aiPrompts' => [
                'analysis' => ProjectAiPrompts::analysis(),
                'actions' => ProjectAiPrompts::actions(),
            ],
            'urls' => [
                'save' => route('projects.canvas.update', $project),
                'refresh' => route('projects.canvas.refresh', $project),
            ],
        ]);
    }

    public function refresh(Project $project, CanvasLayoutService $canvasLayoutService): JsonResponse
    {
        $project->load([
            'funnels.stages',
            'integrations',
            'skkFields.funnelStage.funnel',
        ]);

        $blocks = $canvasLayoutService->syncBlocks($project);

        return response()->json([
            'blocks' => $this->serializeBlocks($project, $blocks, $canvasLayoutService),
            'title' => $project->company_name,
        ]);
    }

    public function update(UpdateCanvasLayoutRequest $request, Project $project): JsonResponse
    {
        if ($request->has('blocks')) {
            $blockIds = $project->canvasBlocks()->pluck('id');

            foreach ($request->validated('blocks') as $blockData) {
                if (! $blockIds->contains($blockData['id'])) {
                    abort(403);
                }

                $update = [
                    'pos_x' => $blockData['pos_x'],
                    'pos_y' => $blockData['pos_y'],
                ];

                if (isset($blockData['z_index'])) {
                    $update['z_index'] = $blockData['z_index'];
                }

                $project->canvasBlocks()
                    ->where('id', $blockData['id'])
                    ->update($update);
            }
        }

        if ($request->has('viewport')) {
            $viewport = $request->validated('viewport');

            $project->update([
                'canvas_offset_x' => $viewport['offset_x'],
                'canvas_offset_y' => $viewport['offset_y'],
                'canvas_zoom' => $viewport['zoom'],
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function destroyBlock(
        Project $project,
        ProjectCanvasBlock $canvasBlock,
        CanvasBlockDeletionService $deletionService
    ): JsonResponse {
        try {
            $message = $deletionService->delete($project, $canvasBlock);

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProject(Project $project): array
    {
        return [
            'id' => $project->id,
            'company_name' => $project->company_name,
            'business_sphere' => $project->business_sphere,
            'employees_count' => $project->employees_count,
            'client_tasks' => $project->client_tasks,
            ...ProjectAccessPresenter::forUser($project, request()->user()),
            'urls' => [
                'show' => route('projects.show', $project),
                'update' => route('projects.update', $project),
                'funnels_store' => route('projects.funnels.store', $project),
                'skk_fields_store' => route('projects.skk-fields.store', $project),
                'integrations_store' => route('projects.integrations.store', $project),
                ...ProjectAiUrls::forProject($project),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeBlocks(Project $project, $blocks, CanvasLayoutService $canvasLayoutService): array
    {
        return $blocks->map(function (ProjectCanvasBlock $block) use ($project, $canvasLayoutService) {
            $width = $block->block_type === ProjectCanvasBlock::TYPE_FUNNEL
                ? max($block->width, 600)
                : max($block->width, 380);

            $isDeletable = in_array($block->block_type, [
                ProjectCanvasBlock::TYPE_SKK,
                ProjectCanvasBlock::TYPE_INTEGRATIONS,
                ProjectCanvasBlock::TYPE_FUNNEL,
            ], true);

            $data = [
                'id' => $block->id,
                'block_type' => $block->block_type,
                'block_key' => $block->block_key,
                'title' => $block->title,
                'pos_x' => $block->pos_x,
                'pos_y' => $block->pos_y,
                'width' => $width,
                'z_index' => $block->z_index,
                'is_deletable' => $isDeletable,
                'destroy_url' => $isDeletable
                    ? route('projects.canvas.blocks.destroy', [$project, $block])
                    : null,
            ];

            if ($block->block_type === ProjectCanvasBlock::TYPE_SKK) {
                $entityType = $canvasLayoutService->entityTypeForBlock($block);
                $data['entity_type'] = $entityType;
                $data['skk_fields'] = $project->skkFields
                    ->where('entity_type', $entityType)
                    ->values()
                    ->map(fn ($field) => SkkFieldPresenter::forFrontend($field, $project))
                    ->all();
            }

            if ($block->block_type === ProjectCanvasBlock::TYPE_INTEGRATIONS) {
                $data['integrations'] = $project->integrations->map(
                    fn ($integration) => IntegrationPresenter::forFrontend($integration, $project)
                )->values()->all();
            }

            if ($block->block_type === ProjectCanvasBlock::TYPE_FUNNEL) {
                $funnel = $project->funnels->firstWhere('id', $block->reference_id);

                if ($funnel) {
                    $data['funnel'] = [
                        'id' => $funnel->id,
                        'name' => $funnel->name,
                        'description' => $funnel->description,
                        'stages' => $funnel->stages->map(fn ($stage, $index) => [
                            'number' => $index + 1,
                            'name' => $stage->name,
                            'action' => $stage->action,
                            'deadline' => $stage->deadline,
                            'kind' => $stage->kind ?: 'normal',
                        ])->values()->all(),
                        'canvas_url' => route('projects.funnels.canvas.show', [$project, $funnel]),
                    ];
                }
            }

            return $data;
        })->values()->all();
    }
}

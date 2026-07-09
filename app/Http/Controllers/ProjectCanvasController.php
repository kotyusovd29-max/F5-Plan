<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsForCanvas;
use App\Http\Requests\UpdateCanvasLayoutRequest;
use App\Models\Project;
use App\Models\ProjectCanvasBlock;
use App\Services\CanvasBlockDeletionService;
use App\Services\CanvasLayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ProjectCanvasController extends Controller
{
    public function show(Project $project, CanvasLayoutService $canvasLayoutService): View
    {
        $project->load([
            'user',
            'funnels.stages',
            'integrations',
            'skkFields',
        ]);

        $blocks = $canvasLayoutService->syncBlocks($project);

        return view('projects.canvas.show', compact('project', 'blocks'));
    }

    public function refresh(Project $project, CanvasLayoutService $canvasLayoutService): JsonResponse
    {
        $project->load([
            'funnels.stages',
            'integrations',
            'skkFields',
        ]);

        $blocks = $canvasLayoutService->syncBlocks($project);

        return response()->json([
            'html' => view('projects.canvas._surface', compact('project', 'blocks'))->render(),
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
}

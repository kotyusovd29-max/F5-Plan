<?php

namespace App\Http\Controllers;

use App\Exceptions\F5AiException;
use App\Http\Requests\ApplyProjectAiRequest;
use App\Http\Requests\GenerateProjectAiRequest;
use App\Models\Project;
use App\Services\F5AiService;
use App\Services\ProjectAiApplier;
use App\Services\ProjectAiPlanner;
use App\Services\ProjectAiProposalParser;
use Illuminate\Http\JsonResponse;

class ProjectAiController extends Controller
{
    public function generate(
        GenerateProjectAiRequest $request,
        Project $project,
        ProjectAiPlanner $planner,
        F5AiService $f5AiService,
        ProjectAiProposalParser $parser
    ): JsonResponse {
        try {
            $input = $request->validated();
            $messages = $planner->buildMessages($project, $input);
            $content = $f5AiService->chatCompletion($messages, [
                'user' => $request->user(),
            ]);
            $proposal = $parser->parse($content);

            return response()->json([
                'success' => true,
                'proposal' => $proposal,
                'summary' => $this->buildSummary($proposal, $input['sections']),
            ]);
        } catch (F5AiException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function apply(
        ApplyProjectAiRequest $request,
        Project $project,
        ProjectAiApplier $applier
    ): JsonResponse {
        $validated = $request->validated();
        $counts = $applier->apply($project, $validated['proposal'], $validated['sections']);

        $parts = [];

        if ($counts['skk_fields'] > 0) {
            $parts[] = $counts['skk_fields'].' '.self::plural($counts['skk_fields'], 'поле', 'поля', 'полей');
        }

        if ($counts['integrations'] > 0) {
            $parts[] = $counts['integrations'].' '.self::plural($counts['integrations'], 'интеграция', 'интеграции', 'интеграций');
        }

        if ($counts['funnels'] > 0) {
            $parts[] = $counts['funnels'].' '.self::plural($counts['funnels'], 'воронка', 'воронки', 'воронок');
        }

        if ($counts['stages'] > 0) {
            $parts[] = $counts['stages'].' '.self::plural($counts['stages'], 'этап', 'этапа', 'этапов');
        }

        $message = $parts === []
            ? 'Нечего применять — в предложении нет данных для выбранных разделов.'
            : 'Добавлено: '.implode(', ', $parts).'.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'counts' => $counts,
            'redirect' => route('projects.show', $project),
        ]);
    }

    /**
     * @param  array<string, mixed>  $proposal
     * @param  array<int, string>  $sections
     * @return array<string, int>
     */
    private function buildSummary(array $proposal, array $sections): array
    {
        $summary = [];

        if (in_array('skk_fields', $sections, true)) {
            $summary['skk_fields'] = collect($proposal['skk_fields'] ?? [])
                ->flatten(1)
                ->count();
        }

        if (in_array('integrations', $sections, true)) {
            $summary['integrations'] = count($proposal['integrations'] ?? []);
        }

        if (in_array('funnels', $sections, true)) {
            $summary['funnels'] = count($proposal['funnels'] ?? []);
            $summary['stages'] = collect($proposal['funnels'] ?? [])
                ->sum(fn (array $funnel) => count($funnel['stages'] ?? []));
        }

        return $summary;
    }

    private static function plural(int $count, string $one, string $few, string $many): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return $one;
        }

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) {
            return $few;
        }

        return $many;
    }
}

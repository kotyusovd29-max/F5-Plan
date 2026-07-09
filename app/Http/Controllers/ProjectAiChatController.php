<?php

namespace App\Http\Controllers;

use App\Exceptions\F5AiException;
use App\Http\Requests\ApplyProjectAiChangesRequest;
use App\Http\Requests\SendProjectAiMessageRequest;
use App\Models\Project;
use App\Models\ProjectAiMessage;
use App\Services\ProjectAiChangesApplier;
use App\Services\ProjectAiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ProjectAiChatController extends Controller
{
    public function show(Project $project): View
    {
        $project->load('user');

        $messages = $project->aiMessages()
            ->orderBy('id')
            ->get();

        return view('projects.ai.chat', compact('project', 'messages'));
    }

    public function send(
        SendProjectAiMessageRequest $request,
        Project $project,
        ProjectAiChatService $chatService
    ): JsonResponse {
        set_time_limit((int) config('f5ai.timeout', 300));

        try {
            $result = $chatService->sendMessage($project, $request->validated('message'));

            return response()->json([
                'success' => true,
                'messages' => [
                    $this->formatMessage($result['user']),
                    $this->formatMessage($result['assistant']),
                ],
                'processing_mode' => mb_strlen($request->validated('message')) >= 1500 ? 'implementation' : 'chat',
            ]);
        } catch (F5AiException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function apply(
        ApplyProjectAiChangesRequest $request,
        Project $project,
        ProjectAiMessage $message,
        ProjectAiChangesApplier $applier
    ): JsonResponse {
        abort_unless($message->project_id === $project->id, 404);
        abort_unless($message->role === ProjectAiMessage::ROLE_ASSISTANT, 422);
        abort_unless($message->hasPendingChanges(), 422);

        $changes = $message->proposalChanges();

        if ($changes === null) {
            return response()->json([
                'success' => false,
                'message' => 'Нет данных для применения.',
            ], 422);
        }

        $counts = $applier->apply($project, $changes);
        $message->update(['applied_at' => now()]);

        $parts = [];

        if ($counts['created'] > 0) {
            $parts[] = 'создано: '.$counts['created'];
        }

        if ($counts['updated'] > 0) {
            $parts[] = 'обновлено: '.$counts['updated'];
        }

        if ($counts['deleted'] > 0) {
            $parts[] = 'удалено: '.$counts['deleted'];
        }

        $summary = $parts === [] ? 'Изменения применены.' : 'Изменения применены ('.implode(', ', $parts).').';

        return response()->json([
            'success' => true,
            'message' => $summary,
            'redirect' => route('projects.ai.chat', $project),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMessage(ProjectAiMessage $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'changes' => $message->proposalChanges(),
            'has_pending_changes' => $message->hasPendingChanges(),
            'applied_at' => $message->applied_at?->toIso8601String(),
            'created_at' => $message->created_at?->format('d.m.Y H:i'),
        ];
    }
}

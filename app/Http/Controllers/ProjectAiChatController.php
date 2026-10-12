<?php

namespace App\Http\Controllers;

use App\Exceptions\F5AiException;
use App\Http\Controllers\Concerns\ExportsToGoogleDocs;
use App\Http\Requests\ApplyProjectAiChangesRequest;
use App\Http\Requests\SendProjectAiMessageRequest;
use App\Models\Project;
use App\Models\ProjectAiMessage;
use App\Services\GoogleDocsExportService;
use App\Services\GoogleOAuthService;
use App\Services\AuditRecommendationsDocxExporter;
use App\Services\MeetingAiMessageDocxExporter;
use App\Services\ProjectAiBackgroundDispatcher;
use App\Models\ProjectAiApplySnapshot;
use App\Services\ProjectAiChangesApplier;
use App\Services\ProjectAiChatService;
use App\Services\ProjectAiConsultationService;
use App\Services\ProjectAiSnapshotService;
use App\Support\ProjectAccessPresenter;
use App\Support\ProjectAiPrompts;
use App\Support\ProjectAiUrls;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectAiChatController extends Controller
{
    use ExportsToGoogleDocs;
    public function show(Project $project): Response
    {
        $project->load('user');

        return Inertia::render('Projects/Ai/Chat', [
            'project' => $this->serializeProject($project),
            'messages' => $this->loadMessages($project, ProjectAiMessage::THREAD_PROJECT),
            'meetingMessages' => $this->loadMessages($project, ProjectAiMessage::THREAD_MEETING),
            'auditRecommendationMessages' => $this->loadMessages(
                $project,
                ProjectAiMessage::THREAD_AUDIT_RECOMMENDATIONS
            ),
            'kpMessages' => $this->loadMessages($project, ProjectAiMessage::THREAD_KP_GENERATION),
            'aiPrompts' => [
                'analysis' => ProjectAiPrompts::analysis(),
                'actions' => ProjectAiPrompts::actions(),
                'meetingActions' => ProjectAiPrompts::meetingActions(),
                'auditRecommendationActions' => ProjectAiPrompts::auditRecommendationActions(),
            ],
            'chatBootstrap' => [
                'prompt' => request()->string('prompt')->toString() ?: null,
                'mode' => request()->string('mode')->toString() ?: null,
                'autostart' => request()->boolean('autostart'),
                'thread' => request()->string('thread')->toString() ?: ProjectAiMessage::THREAD_PROJECT,
            ],
        ]);
    }

    public function messages(Project $project, ProjectAiChatService $chatService): JsonResponse
    {
        $chatService->recoverStaleProcessingMessages($project);

        $thread = request()->string('thread')->toString() ?: ProjectAiMessage::THREAD_PROJECT;

        if (! in_array($thread, ProjectAiMessage::threads(), true)) {
            $thread = ProjectAiMessage::THREAD_PROJECT;
        }

        return response()->json([
            'success' => true,
            'thread' => $thread,
            'messages' => $this->loadMessages($project, $thread),
        ]);
    }

    public function send(
        SendProjectAiMessageRequest $request,
        Project $project,
        ProjectAiChatService $chatService
    ): JsonResponse {
        $message = $request->validated('message');
        $mode = $request->validated('mode');
        $thread = $request->validated('thread') ?? ProjectAiMessage::THREAD_PROJECT;
        $briefDocumentIds = $request->validated('brief_document_ids') ?? [];
        $singleDocumentId = $request->validated('brief_document_id');

        if ($briefDocumentIds === [] && $singleDocumentId) {
            $briefDocumentIds = [(int) $singleDocumentId];
        }

        if (in_array($thread, [
            ProjectAiMessage::THREAD_MEETING,
            ProjectAiMessage::THREAD_AUDIT_RECOMMENDATIONS,
            ProjectAiMessage::THREAD_KP_GENERATION,
        ], true)) {
            $mode = null;
        }

        if ($thread === ProjectAiMessage::THREAD_KP_GENERATION
            && ! $request->user()?->hasGoogleConnected()
        ) {
            return $this->googleDocsConnectPayload(app(GoogleOAuthService::class));
        }

        try {
            $queued = $chatService->enqueueMessage(
                $project,
                $message,
                $mode,
                $briefDocumentIds !== [] ? $briefDocumentIds : null,
                $thread,
                $request->user()?->id
            );

            if (app()->environment('testing')) {
                $chatService->processPendingResponse(
                    $project,
                    $queued['user'],
                    $queued['assistant'],
                    $mode
                );
            } else {
                app(ProjectAiBackgroundDispatcher::class)->dispatch(
                    $project->id,
                    $queued['user']->id,
                    $queued['assistant']->id,
                    $mode
                );
            }

            $processingMode = match ($thread) {
                ProjectAiMessage::THREAD_MEETING => 'meeting',
                ProjectAiMessage::THREAD_AUDIT_RECOMMENDATIONS => 'audit_recommendations',
                ProjectAiMessage::THREAD_KP_GENERATION => 'kp_generation',
                default => $mode ?? (mb_strlen($message) >= 1500 ? 'implementation' : 'chat'),
            };

            return response()->json([
                'success' => true,
                'processing' => true,
                'messages' => [
                    $this->formatMessage($queued['user'], $project),
                    $this->formatMessage($queued['assistant'], $project),
                ],
                'processing_mode' => $processingMode,
                'thread' => $thread,
            ]);
        } catch (F5AiException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось отправить сообщение. Попробуйте снова.',
            ], 500);
        }
    }

    public function analyze(Project $project, ProjectAiConsultationService $consultation): JsonResponse
    {
        set_time_limit((int) config('f5ai.timeout', 300));

        try {
            $analysis = $consultation->generateContent(
                $project,
                'project_analysis',
                ProjectAiPrompts::analysis()['message']
            );

            return response()->json([
                'success' => true,
                'analysis' => $analysis,
            ]);
        } catch (F5AiException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось выполнить анализ проекта. Попробуйте снова.',
            ], 500);
        }
    }

    public function cancel(
        Project $project,
        ProjectAiMessage $message,
        ProjectAiChatService $chatService
    ): JsonResponse {
        if ($message->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Сообщение не найдено в этом проекте.',
            ], 404);
        }

        if ($message->role !== ProjectAiMessage::ROLE_ASSISTANT) {
            return response()->json([
                'success' => false,
                'message' => 'Отменять можно только ответы ИИ.',
            ], 422);
        }

        if (! $chatService->cancelMessage($project, $message)) {
            return response()->json([
                'success' => false,
                'message' => 'Запрос уже завершён или не обрабатывается.',
            ], 422);
        }

        $message->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Запрос отменён.',
            'thread' => $message->thread,
            'assistant_message' => $this->formatMessage($message, $project),
        ]);
    }

    public function apply(
        ApplyProjectAiChangesRequest $request,
        Project $project,
        ProjectAiMessage $message,
        ProjectAiChangesApplier $applier,
        ProjectAiSnapshotService $snapshots
    ): JsonResponse {
        if ($message->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Сообщение не найдено в этом проекте.',
            ], 404);
        }

        if ($message->role !== ProjectAiMessage::ROLE_ASSISTANT) {
            return response()->json([
                'success' => false,
                'message' => 'Применять можно только ответы ИИ.',
            ], 422);
        }

        if (! $message->hasPendingChanges()) {
            return response()->json([
                'success' => false,
                'message' => 'Изменения уже применены или отсутствуют.',
            ], 422);
        }

        $changes = $message->proposalChanges();

        if ($changes === null) {
            return response()->json([
                'success' => false,
                'message' => 'Нет данных для применения.',
            ], 422);
        }

        try {
            $snapshots->capture($project, $message);
            $counts = $applier->apply($project, $changes);
            $message->update(['applied_at' => now()]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось применить изменения. Проверьте данные в ответе ИИ и попробуйте снова.',
            ], 500);
        }

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

        $message->refresh();

        return response()->json([
            'success' => true,
            'message' => $summary.' Можно откатить.',
            'assistant_message' => $this->formatMessage($message, $project),
            'redirect' => route('projects.ai.chat', $project),
        ]);
    }

    public function rollback(
        Project $project,
        ProjectAiMessage $message,
        ProjectAiSnapshotService $snapshots
    ): JsonResponse {
        if ($message->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Сообщение не найдено в этом проекте.',
            ], 404);
        }

        if ($message->role !== ProjectAiMessage::ROLE_ASSISTANT || $message->applied_at === null) {
            return response()->json([
                'success' => false,
                'message' => 'Откат доступен только для применённых изменений ИИ.',
            ], 422);
        }

        $snapshot = ProjectAiApplySnapshot::query()
            ->where('project_ai_message_id', $message->id)
            ->where('project_id', $project->id)
            ->first();

        if ($snapshot === null || ! $snapshot->canRestore()) {
            return response()->json([
                'success' => false,
                'message' => 'Снимок для отката не найден или уже восстановлен.',
            ], 422);
        }

        try {
            $snapshots->restore($snapshot);
            $message->update(['applied_at' => null]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось откатить изменения.',
            ], 500);
        }

        $message->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Изменения ИИ откатены. Проект возвращён к состоянию до применения.',
            'assistant_message' => $this->formatMessage($message, $project),
            'redirect' => route('projects.ai.chat', $project),
        ]);
    }

    public function exportMeetingDocx(
        Project $project,
        ProjectAiMessage $message
    ): BinaryFileResponse {
        $this->assertExportableAssistantMessage($project, $message);

        $exporter = $this->docxExporterForThread($message->thread);
        $tempFile = $exporter->build($project, $message);

        return response()
            ->download($tempFile, $exporter->filename($project, $message))
            ->deleteFileAfterSend();
    }

    public function downloadKpPptx(
        Project $project,
        ProjectAiMessage $message
    ): BinaryFileResponse {
        abort_unless($message->project_id === $project->id, 404);
        abort_unless($message->thread === ProjectAiMessage::THREAD_KP_GENERATION, 404);

        $pptxPath = "project-kp/{$project->id}/message-{$message->id}.pptx";

        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($pptxPath), 404);

        $document = \App\Models\ProjectGoogleDocument::query()
            ->where('project_ai_message_id', $message->id)
            ->latest('id')
            ->first();

        $filename = ($document?->name ?? 'КП '.$project->company_name).'.pptx';

        return response()->download(
            \Illuminate\Support\Facades\Storage::disk('local')->path($pptxPath),
            preg_replace('/[\/\\\\]+/u', '_', $filename)
        );
    }

    public function exportMeetingGoogleDocs(
        Project $project,
        ProjectAiMessage $message,
        GoogleOAuthService $oauth,
        GoogleDocsExportService $googleDocs
    ): JsonResponse {
        $this->assertExportableAssistantMessage($project, $message);

        $exporter = $this->docxExporterForThread($message->thread);
        $tempFile = $exporter->build($project, $message);

        $source = $message->thread === ProjectAiMessage::THREAD_AUDIT_RECOMMENDATIONS
            ? \App\Models\ProjectGoogleDocument::SOURCE_AUDIT_RECOMMENDATIONS
            : \App\Models\ProjectGoogleDocument::SOURCE_MEETING;

        return $this->exportDocxPathToGoogleDocs(
            request()->user(),
            $project,
            $tempFile,
            $exporter->filename($project, $message),
            $oauth,
            $googleDocs,
            [
                'source' => $source,
                'project_ai_message_id' => $message->id,
            ]
        );
    }

    private function assertExportableAssistantMessage(Project $project, ProjectAiMessage $message): void
    {
        abort_unless($message->project_id === $project->id, 404);
        abort_unless($message->isExportableThread(), 404);
        abort_unless($message->role === ProjectAiMessage::ROLE_ASSISTANT, 404);
        abort_unless($message->status === ProjectAiMessage::STATUS_COMPLETED, 422);
        abort_unless(trim((string) $message->content) !== '', 422);
    }

    private function docxExporterForThread(string $thread): MeetingAiMessageDocxExporter|AuditRecommendationsDocxExporter
    {
        return match ($thread) {
            ProjectAiMessage::THREAD_AUDIT_RECOMMENDATIONS => app(AuditRecommendationsDocxExporter::class),
            default => app(MeetingAiMessageDocxExporter::class),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadMessages(Project $project, string $thread = ProjectAiMessage::THREAD_PROJECT): array
    {
        return $project->aiMessages()
            ->with('briefDocument')
            ->where('thread', $thread)
            ->orderBy('id')
            ->get()
            ->map(fn (ProjectAiMessage $message) => $this->formatMessage($message, $project))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProject(Project $project): array
    {
        $project->loadMissing('user');

        return [
            'id' => $project->id,
            'company_name' => $project->company_name,
            'author' => $project->user->name,
            ...ProjectAccessPresenter::forUser($project, request()->user()),
            'urls' => [
                'home' => route('home'),
                'show' => route('projects.show', $project),
                'export_docx' => route('projects.export.docx', $project),
                'export_google_docs' => route('projects.export.google-docs', $project),
                'documents' => route('projects.documents.index', $project),
                'canvas' => route('projects.canvas.show', $project),
                ...ProjectAiUrls::forProject($project),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMessage(ProjectAiMessage $message, ?Project $project = null): array
    {
        $project ??= $message->project;
        $message->loadMissing(['briefDocument', 'applySnapshot']);

        $canRollback = $project
            && $message->applied_at !== null
            && $message->applySnapshot
            && $message->applySnapshot->canRestore();

        $data = [
            'id' => $message->id,
            'role' => $message->role,
            'status' => $message->status,
            'content' => $message->content,
            'error_message' => $message->error_message,
            'changes' => $message->proposalChanges(),
            'has_pending_changes' => $message->hasPendingChanges(),
            'applied_at' => $message->applied_at?->toIso8601String(),
            'can_rollback' => $canRollback,
            'created_at' => $message->created_at?->format('d.m.Y H:i'),
            'brief_document' => $message->briefDocument ? [
                'id' => $message->briefDocument->id,
                'original_name' => $message->briefDocument->original_name,
                'is_image' => $message->briefDocument->isImage(),
                'mime_type' => $message->briefDocument->mime_type,
                'size_label' => \App\Support\BriefDocumentPresenter::formatSize(
                    (int) $message->briefDocument->size
                ),
            ] : null,
            'brief_documents' => $message->briefDocuments()
                ->map(fn ($document) => [
                    'id' => $document->id,
                    'original_name' => $document->original_name,
                    'is_image' => $document->isImage(),
                    'mime_type' => $document->mime_type,
                    'size_label' => \App\Support\BriefDocumentPresenter::formatSize((int) $document->size),
                ])
                ->values()
                ->all(),
        ];

        if ($project && $message->hasPendingChanges()) {
            $data['apply_url'] = route('projects.ai.chat.apply', [$project, $message]);
        }

        if ($canRollback) {
            $data['rollback_url'] = route('projects.ai.chat.rollback', [$project, $message]);
        }

        if (
            $project
            && $message->isExportableThread()
            && $message->role === ProjectAiMessage::ROLE_ASSISTANT
            && $message->status === ProjectAiMessage::STATUS_COMPLETED
            && trim((string) $message->content) !== ''
        ) {
            $data['export_docx_url'] = route('projects.ai.chat.export-docx', [$project, $message]);
            $data['export_google_docs_url'] = route('projects.ai.chat.export-google-docs', [$project, $message]);
        }

        if ($message->thread === ProjectAiMessage::THREAD_KP_GENERATION
            && $message->role === ProjectAiMessage::ROLE_ASSISTANT
            && $message->status === ProjectAiMessage::STATUS_COMPLETED
        ) {
            $document = \App\Models\ProjectGoogleDocument::query()
                ->where('project_ai_message_id', $message->id)
                ->latest('id')
                ->first();

            if ($document !== null) {
                $pptxPath = "project-kp/{$project->id}/message-{$message->id}.pptx";

                $data['kp_document'] = [
                    'name' => $document->name,
                    'url' => $document->url,
                    'download_url' => \Illuminate\Support\Facades\Storage::disk('local')->exists($pptxPath)
                        ? route('projects.ai.chat.kp-pptx', [$project, $message])
                        : null,
                ];
            }
        }

        return $data;
    }
}

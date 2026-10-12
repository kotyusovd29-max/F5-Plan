<?php

namespace App\Http\Controllers;

use App\Exceptions\F5AiException;
use App\Http\Requests\StoreProjectBriefDocumentRequest;
use App\Models\Project;
use App\Models\ProjectAiMessage;
use App\Models\ProjectBriefDocument;
use App\Services\BriefDocumentTextExtractor;
use App\Services\ProjectAiBackgroundDispatcher;
use App\Services\ProjectAiChatService;
use App\Support\BriefDocumentPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectBriefDocumentController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        $documents = $project->briefDocuments()
            ->latest('id')
            ->get()
            ->map(fn (ProjectBriefDocument $document) => BriefDocumentPresenter::forFrontend($document, $project))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'documents' => $documents,
        ]);
    }

    public function store(
        StoreProjectBriefDocumentRequest $request,
        Project $project,
        BriefDocumentTextExtractor $extractor
    ): JsonResponse {
        try {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            $mimeType = strtolower((string) $file->getMimeType());
            $isImage = str_starts_with($mimeType, 'image/')
                || in_array($extension, ProjectBriefDocument::IMAGE_EXTENSIONS, true);

            $extracted = $isImage
                ? ['text' => null, 'char_count' => 0, 'truncated' => false]
                : $extractor->extract($file);

            $filename = Str::uuid()->toString().'.'.$extension;
            $diskPath = "project-briefs/{$project->id}/{$filename}";
            $kind = $request->input('kind', ProjectBriefDocument::KIND_TZ);

            if (! in_array($kind, ProjectBriefDocument::KINDS, true)) {
                $kind = ProjectBriefDocument::KIND_TZ;
            }

            Storage::disk('local')->putFileAs(
                "project-briefs/{$project->id}",
                $file,
                $filename
            );

            $document = $project->briefDocuments()->create([
                'user_id' => $request->user()->id,
                'kind' => $kind,
                'original_name' => $file->getClientOriginalName(),
                'disk_path' => $diskPath,
                'mime_type' => $file->getMimeType() ?: ($isImage ? 'image/'.$extension : null),
                'size' => $file->getSize(),
                'extracted_text' => $extracted['text'],
                'char_count' => $extracted['char_count'],
            ]);

            $label = match ($kind) {
                ProjectBriefDocument::KIND_KP => 'КП',
                ProjectBriefDocument::KIND_CHAT => 'для чата',
                default => 'ТЗ',
            };

            $message = $isImage
                ? 'Фото загружено. При отправке в чат текст со скриншота будет распознан для ИИ.'
                : "Документ {$label} загружен.";

            return response()->json([
                'success' => true,
                'message' => $message,
                'document' => BriefDocumentPresenter::forFrontend($document, $project),
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
                'message' => 'Не удалось загрузить документ. Попробуйте другой файл.',
            ], 500);
        }
    }

    public function destroy(Project $project, ProjectBriefDocument $briefDocument): JsonResponse
    {
        abort_unless($briefDocument->project_id === $project->id, 404);

        if ($briefDocument->disk_path && Storage::disk('local')->exists($briefDocument->disk_path)) {
            Storage::disk('local')->delete($briefDocument->disk_path);
        }

        $briefDocument->delete();

        return response()->json([
            'success' => true,
            'message' => 'Документ удалён.',
        ]);
    }

    public function generatePlan(
        Request $request,
        Project $project,
        ProjectBriefDocument $briefDocument,
        ProjectAiChatService $chatService
    ): JsonResponse {
        abort_unless($briefDocument->project_id === $project->id, 404);

        if (trim((string) $briefDocument->extracted_text) === '') {
            return response()->json([
                'success' => false,
                'message' => 'В документе нет текста для анализа.',
            ], 422);
        }

        try {
            $message = "Сформировать план внедрения по ТЗ из документа «{$briefDocument->original_name}».";

            $queued = $chatService->enqueueMessage(
                $project,
                $message,
                'tz_plan',
                $briefDocument->id,
                ProjectAiMessage::THREAD_PROJECT,
                $request->user()?->id
            );

            if (app()->environment('testing')) {
                $chatService->processPendingResponse(
                    $project,
                    $queued['user'],
                    $queued['assistant'],
                    'tz_plan'
                );
            } else {
                app(ProjectAiBackgroundDispatcher::class)->dispatch(
                    $project->id,
                    $queued['user']->id,
                    $queued['assistant']->id,
                    'tz_plan'
                );
            }

            return response()->json([
                'success' => true,
                'processing' => true,
                'processing_mode' => 'tz_plan',
                'messages' => [
                    $this->formatMessage($queued['user'], $project),
                    $this->formatMessage($queued['assistant'], $project),
                ],
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
                'message' => 'Не удалось запустить формирование плана по ТЗ.',
            ], 500);
        }
    }

    public function generateWorkStages(
        Request $request,
        Project $project,
        ProjectBriefDocument $briefDocument,
        ProjectAiChatService $chatService
    ): JsonResponse {
        abort_unless($briefDocument->project_id === $project->id, 404);

        if (trim((string) $briefDocument->extracted_text) === '') {
            return response()->json([
                'success' => false,
                'message' => 'В документе нет текста для анализа.',
            ], 422);
        }

        try {
            $message = "Извлечь этапы работ из КП «{$briefDocument->original_name}» и подготовить их для блока «Этапы работ».";

            $queued = $chatService->enqueueMessage(
                $project,
                $message,
                'kp_work_stages',
                $briefDocument->id,
                ProjectAiMessage::THREAD_PROJECT,
                $request->user()?->id
            );

            if (app()->environment('testing')) {
                $chatService->processPendingResponse(
                    $project,
                    $queued['user'],
                    $queued['assistant'],
                    'kp_work_stages'
                );
            } else {
                app(ProjectAiBackgroundDispatcher::class)->dispatch(
                    $project->id,
                    $queued['user']->id,
                    $queued['assistant']->id,
                    'kp_work_stages'
                );
            }

            return response()->json([
                'success' => true,
                'processing' => true,
                'processing_mode' => 'kp_work_stages',
                'messages' => [
                    $this->formatMessage($queued['user'], $project),
                    $this->formatMessage($queued['assistant'], $project),
                ],
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
                'message' => 'Не удалось запустить анализ КП.',
            ], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMessage(ProjectAiMessage $message, Project $project): array
    {
        $data = [
            'id' => $message->id,
            'role' => $message->role,
            'status' => $message->status,
            'content' => $message->content,
            'error_message' => $message->error_message,
            'changes' => $message->proposalChanges(),
            'has_pending_changes' => $message->hasPendingChanges(),
            'applied_at' => $message->applied_at?->toIso8601String(),
            'created_at' => $message->created_at?->format('d.m.Y H:i'),
        ];

        if ($message->hasPendingChanges()) {
            $data['apply_url'] = route('projects.ai.chat.apply', [$project, $message]);
        }

        return $data;
    }
}

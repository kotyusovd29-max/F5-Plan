<?php

namespace App\Services;

use App\Exceptions\F5AiException;
use App\Exceptions\GoogleDocsException;
use App\Models\Project;
use App\Models\ProjectAiMessage;
use App\Support\F5AiModels;
use App\Support\F5WidgetCatalog;
use App\Support\WorkStageCostPricing;
use Illuminate\Support\Facades\Storage;

class ProjectAiChatService
{
    private const HISTORY_LIMIT = 12;

    public function __construct(
        private readonly F5AiService $f5AiService,
        private readonly ProjectAiContextBuilder $contextBuilder,
        private readonly ProjectAiChangesParser $changesParser,
        private readonly ProjectAiImplementationService $implementationService,
        private readonly ProjectAiConsultationService $consultationService,
        private readonly ProjectAiTzPlanService $tzPlanService,
        private readonly ProjectAiKpWorkStagesService $kpWorkStagesService,
        private readonly BriefImageOcrService $imageOcrService,
    ) {}

    /**
     * @param  int|list<int>|null  $briefDocumentIds
     * @return array{user: ProjectAiMessage, assistant: ProjectAiMessage}
     */
    public function enqueueMessage(
        Project $project,
        string $message,
        ?string $mode = null,
        int|array|null $briefDocumentIds = null,
        string $thread = ProjectAiMessage::THREAD_PROJECT,
        ?int $userId = null
    ): array {
        $this->recoverStaleProcessingMessages($project);

        $documentIds = $this->normalizeDocumentIds($briefDocumentIds);

        $userMessage = $project->aiMessages()->create([
            'thread' => $thread,
            'user_id' => $userId,
            'role' => ProjectAiMessage::ROLE_USER,
            'content' => $message,
            'status' => ProjectAiMessage::STATUS_COMPLETED,
            'brief_document_id' => $documentIds[0] ?? null,
            'brief_document_ids' => $documentIds !== [] ? $documentIds : null,
        ]);

        $assistantMessage = $project->aiMessages()->create([
            'thread' => $thread,
            'user_id' => $userId,
            'role' => ProjectAiMessage::ROLE_ASSISTANT,
            'content' => '',
            'status' => ProjectAiMessage::STATUS_PROCESSING,
        ]);

        return [
            'user' => $userMessage,
            'assistant' => $assistantMessage,
        ];
    }

    public function processPendingResponse(
        Project $project,
        ProjectAiMessage $userMessage,
        ProjectAiMessage $assistantMessage,
        ?string $mode = null
    ): void {
        $assistantMessage->refresh();

        if ($assistantMessage->status !== ProjectAiMessage::STATUS_PROCESSING) {
            return;
        }

        set_time_limit($this->processingTimeLimit($mode, $userMessage->thread));

        $userMessage->loadMissing('briefDocument');

        try {
            $result = $this->buildResponse($project, $userMessage, $assistantMessage, $userMessage->content, $mode);

            $assistantMessage->refresh();

            if ($assistantMessage->status !== ProjectAiMessage::STATUS_PROCESSING) {
                return;
            }

            $assistantMessage->update([
                'content' => $result['content'],
                'changes' => $result['changes'],
                'status' => ProjectAiMessage::STATUS_COMPLETED,
                'error_message' => null,
            ]);

            app(UserNotificationService::class)->notifyAiReady($project, $assistantMessage->fresh());
        } catch (F5AiException $exception) {
            $assistantMessage->refresh();

            if ($assistantMessage->status !== ProjectAiMessage::STATUS_PROCESSING) {
                return;
            }

            $assistantMessage->update([
                'content' => $this->failureContent($userMessage),
                'status' => ProjectAiMessage::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ]);

            app(UserNotificationService::class)->notifyAiReady($project, $assistantMessage->fresh());
        } catch (\Throwable $exception) {
            report($exception);

            $assistantMessage->refresh();

            if ($assistantMessage->status !== ProjectAiMessage::STATUS_PROCESSING) {
                return;
            }

            $assistantMessage->update([
                'content' => $this->failureContent($userMessage),
                'status' => ProjectAiMessage::STATUS_FAILED,
                'error_message' => 'Внутренняя ошибка сервера. Попробуйте снова.',
            ]);

            app(UserNotificationService::class)->notifyAiReady($project, $assistantMessage->fresh());
        }
    }

    private function failureContent(ProjectAiMessage $userMessage): string
    {
        return $userMessage->thread === ProjectAiMessage::THREAD_KP_GENERATION
            ? 'Не удалось собрать презентацию.'
            : 'Не удалось получить ответ от ИИ.';
    }

    public function cancelMessage(Project $project, ProjectAiMessage $assistantMessage): bool
    {
        if ($assistantMessage->project_id !== $project->id) {
            return false;
        }

        if ($assistantMessage->role !== ProjectAiMessage::ROLE_ASSISTANT) {
            return false;
        }

        if ($assistantMessage->status !== ProjectAiMessage::STATUS_PROCESSING) {
            return false;
        }

        $assistantMessage->update([
            'status' => ProjectAiMessage::STATUS_CANCELLED,
            'content' => 'Запрос отменён.',
            'error_message' => 'Запрос отменён пользователем.',
        ]);

        return true;
    }

    /**
     * @return array{content: string, changes: array<string, mixed>|null}
     */
    private function buildResponse(
        Project $project,
        ProjectAiMessage $userMessage,
        ProjectAiMessage $assistantMessage,
        string $message,
        ?string $mode
    ): array {
        if ($userMessage->thread === ProjectAiMessage::THREAD_MEETING) {
            return $this->buildMeetingResponse($project, $userMessage, $assistantMessage, $message);
        }

        if ($userMessage->thread === ProjectAiMessage::THREAD_AUDIT_RECOMMENDATIONS) {
            return $this->buildAuditRecommendationsResponse($project, $userMessage, $assistantMessage, $message);
        }

        if ($userMessage->thread === ProjectAiMessage::THREAD_KP_GENERATION) {
            return $this->buildKpPresentationResponse($project, $userMessage, $assistantMessage, $message);
        }

        if (in_array($mode, ['audit', 'benchmark_b2b', 'project_analysis'], true)) {
            return [
                'content' => $this->consultationService->generateContent(
                    $project,
                    $mode,
                    $this->buildUserApiContent($userMessage, $message),
                    $userMessage->user_id
                ),
                'changes' => null,
            ];
        }

        if ($mode === 'tz_plan') {
            $document = $userMessage->briefDocument;

            if ($document === null || trim((string) $document->extracted_text) === '') {
                throw new F5AiException('Текст ТЗ не найден. Загрузите документ заново.');
            }

            $assistantMessage->update([
                'content' => 'Анализирую документ ТЗ (шаг 1 из 2)…',
            ]);

            $result = $this->tzPlanService->process(
                $project,
                $document->extracted_text,
                function (string $progress) use ($assistantMessage): void {
                    $assistantMessage->refresh();

                    if ($assistantMessage->status !== ProjectAiMessage::STATUS_PROCESSING) {
                        return;
                    }

                    $assistantMessage->update(['content' => $progress]);
                },
                $userMessage->user_id
            );

            return $this->tzPlanResultPayload($result);
        }

        if ($mode === 'kp_work_stages') {
            $document = $userMessage->briefDocument;

            if ($document === null || trim((string) $document->extracted_text) === '') {
                throw new F5AiException('Текст КП не найден. Загрузите документ заново.');
            }

            $assistantMessage->update([
                'content' => 'Читаю коммерческое предложение…',
            ]);

            $result = $this->kpWorkStagesService->process(
                $project,
                $document->extracted_text,
                function (string $progress) use ($assistantMessage): void {
                    $assistantMessage->refresh();

                    if ($assistantMessage->status !== ProjectAiMessage::STATUS_PROCESSING) {
                        return;
                    }

                    $assistantMessage->update(['content' => $progress]);
                },
                $userMessage->user_id
            );

            $result['changes'] = WorkStageCostPricing::normalizeChanges(
                $result['changes'],
                (string) $document->extracted_text
            );

            $footer = $result['changes']
                ? "\n\n---\n**Этапы работ из КП подготовлены.** Нажмите «Применить изменения», чтобы загрузить их в блок «Этапы работ». Существующие этапы из списка удаления будут заменены."
                : '';

            return [
                'content' => $result['report'].$footer,
                'changes' => $result['changes'],
            ];
        }

        if ($this->isRebuildChangesFromPlanRequest($message)) {
            return $this->rebuildChangesFromLastPlan($project, $assistantMessage, $userMessage->user_id);
        }

        $messageForAi = $this->messageWithAttachedDocument($userMessage, $message);

        if (
            $mode !== 'auto_tasks'
            && is_string($messageForAi)
            && $this->implementationService->isImplementationBrief($messageForAi)
        ) {
            $result = $this->implementationService->process($project, $messageForAi, $userMessage->user_id);
            $footer = "\n\n---\n**Настройки проекта подготовлены.** Нажмите «Применить изменения» под этим сообщением, чтобы загрузить воронки, поля и интеграции в проект.";

            return [
                'content' => $result['report'].$footer,
                'changes' => $result['changes'],
            ];
        }

        return $this->buildRegularResponse($project, $userMessage, $assistantMessage, $message, $mode);
    }

    /**
     * @param  int|list<int>|null  $ids
     * @return list<int>
     */
    private function normalizeDocumentIds(int|array|null $ids): array
    {
        if ($ids === null) {
            return [];
        }

        if (! is_array($ids)) {
            $ids = [$ids];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    private function messageWithAttachedDocument(ProjectAiMessage $userMessage, string $message): string
    {
        $documents = $userMessage->briefDocuments();

        if ($documents->isEmpty()) {
            return $message;
        }

        $result = trim($message);

        foreach ($documents as $document) {
            $result = $this->appendDocumentBlock($result, $document);
        }

        return $result;
    }

    private function appendDocumentBlock(string $message, \App\Models\ProjectBriefDocument $document): string
    {
        if ($document->isImage()) {
            $ocrText = $this->imageOcrService->ensureExtractedText($document);
            $name = trim((string) $document->original_name) ?: 'изображение';
            $base = trim($message);

            if ($base === '') {
                $base = 'Проанализируй прикреплённое изображение в контексте внедрения amoCRM и задач проекта.';
            }

            if ($ocrText !== null && $ocrText !== '') {
                return $base."\n\n---\nПрикреплённое изображение «{$name}» (распознанный текст OCR):\n{$ocrText}";
            }

            return $base."\n\n---\nПрикреплено изображение «{$name}», но текст на нём распознать не удалось. "
                .'Попроси пользователя перечислить названия этапов/элементов текстом.';
        }

        $text = trim((string) $document->extracted_text);

        if ($text === '') {
            return $message;
        }

        $name = trim((string) $document->original_name) ?: 'документ';

        return trim($message)."\n\n---\nПрикреплённый документ «{$name}»:\n{$text}";
    }

    /**
     * @return string|list<array<string, mixed>>
     */
    private function buildUserApiContent(ProjectAiMessage $userMessage, string $message): string|array
    {
        $documents = $userMessage->briefDocuments();

        if ($documents->isEmpty()) {
            return $message;
        }

        // F5AI часто отбрасывает image_url — основной канал: OCR-текст в промпте.
        $text = $this->messageWithAttachedDocument($userMessage, $message);

        $parts = [
            [
                'type' => 'text',
                'text' => $text,
            ],
        ];

        $hasImages = false;
        foreach ($documents as $document) {
            if (! $document->isImage()) {
                continue;
            }

            $dataUrl = $document->toDataUrl();
            if ($dataUrl === null) {
                continue;
            }

            $parts[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $dataUrl,
                ],
            ];
            $hasImages = true;
        }

        return $hasImages ? $parts : $text;
    }

    /**
     * @param  array{report: string, changes: array<string, mixed>|null}  $result
     * @return array{content: string, changes: array<string, mixed>|null}
     */
    private function tzPlanResultPayload(array $result): array
    {
        $hasChanges = $result['changes'] !== null;
        $funnelCreates = is_array($result['changes']['funnels']['create'] ?? null)
            ? count($result['changes']['funnels']['create'])
            : 0;
        $reportMentionsFunnels = (bool) preg_match('/воронк/iu', $result['report']);

        if ($hasChanges && ($funnelCreates > 0 || ! $reportMentionsFunnels)) {
            $footer = "\n\n---\n**Настройки проекта подготовлены по ТЗ.** Нажмите «Применить изменения» под этим сообщением, чтобы загрузить план в проект.\nЕсли этапы воронок не совпадают с разделом «Воронки и этапы» выше — напишите в чате: «Собери изменения по плану выше: воронки, поля и интеграции».";
        } elseif ($hasChanges) {
            $footer = "\n\n---\n**План по ТЗ готов**, но воронки не попали в блок изменений.\nНапишите в чате: «Собери изменения по плану выше: воронки, поля и интеграции» — и нажмите «Применить изменения», когда превью появится.";
        } else {
            $footer = "\n\n---\n**План по ТЗ готов** (текстовый разбор выше), но автоматический блок изменений не собрался.\nНапишите в чате: «Собери изменения по плану выше: воронки, поля и интеграции» — и нажмите «Применить изменения», когда превью появится.";
        }

        return [
            'content' => $result['report'].$footer,
            'changes' => $result['changes'],
        ];
    }

    private function isRebuildChangesFromPlanRequest(string $message): bool
    {
        return (bool) preg_match('/собери\s+изменения\s+по\s+плану/ui', $message);
    }

    private function stripTzPlanFooter(string $content): string
    {
        $content = trim((string) preg_replace(
            '/\n---\n\*\*(?:Настройки проекта подготовлены по ТЗ|План по ТЗ готов)[\s\S]*$/u',
            '',
            $content
        ));

        return trim($content);
    }

    /**
     * @return array{content: string, changes: array<string, mixed>|null}
     */
    private function rebuildChangesFromLastPlan(
        Project $project,
        ProjectAiMessage $assistantMessage,
        ?int $preferredUserId = null
    ): array {
        $lastPlanMessage = $project->aiMessages()
            ->where('thread', ProjectAiMessage::THREAD_PROJECT)
            ->where('role', ProjectAiMessage::ROLE_ASSISTANT)
            ->where('status', ProjectAiMessage::STATUS_COMPLETED)
            ->where('id', '<', $assistantMessage->id)
            ->where(function ($query): void {
                $query->where('content', 'like', '%Воронки и этапы%')
                    ->orWhere('content', 'like', '%План по ТЗ%')
                    ->orWhere('content', 'like', '%Настройки проекта подготовлены по ТЗ%');
            })
            ->orderByDesc('id')
            ->first();

        if ($lastPlanMessage === null) {
            throw new F5AiException(
                'Не найден предыдущий план по ТЗ в этом чате. Сначала запустите «План по ТЗ» по документу.'
            );
        }

        $report = $this->stripTzPlanFooter((string) $lastPlanMessage->content);

        if ($report === '') {
            throw new F5AiException('Не удалось прочитать текст предыдущего плана.');
        }

        $assistantMessage->update(['content' => 'Собираю изменения по готовому плану…']);

        $changes = $this->tzPlanService->rebuildChangesFromReport(
            $project,
            $report,
            function (string $progress) use ($assistantMessage): void {
                $assistantMessage->refresh();

                if ($assistantMessage->status !== ProjectAiMessage::STATUS_PROCESSING) {
                    return;
                }

                $assistantMessage->update(['content' => $progress]);
            },
            $preferredUserId
        );

        return $this->tzPlanResultPayload([
            'report' => $report,
            'changes' => $changes,
        ]);
    }

    /**
     * Генерация КП: ИИ планирует колоду, сайт собирает презентацию в Google Slides.
     * Пользователю показывается только кнопка просмотра — «код» остаётся внутри.
     *
     * @return array{content: string, changes: array<string, mixed>|null}
     */
    private function buildKpPresentationResponse(
        Project $project,
        ProjectAiMessage $userMessage,
        ProjectAiMessage $assistantMessage,
        string $message
    ): array {
        $user = $userMessage->user ?? $project->user;

        if ($user === null || ! $user->hasGoogleConnected()) {
            throw new F5AiException(
                'Для генерации презентации подключите Google-аккаунт в личном кабинете.'
            );
        }

        $brief = $this->messageWithAttachedDocument($userMessage, $message);

        try {
            $result = app(AiKpPresentationService::class)->build($project, $user, $user->id, $brief);
        } catch (GoogleDocsException $exception) {
            throw new F5AiException($exception->getMessage());
        }

        // Сохраняем PPTX рядом с сообщением, чтобы ссылка на скачивание не протухала.
        $pptxStoragePath = "project-kp/{$project->id}/message-{$assistantMessage->id}.pptx";
        $pptxContents = is_file($result['pptx_path']) ? file_get_contents($result['pptx_path']) : false;

        if ($pptxContents !== false && $pptxContents !== '') {
            Storage::disk('local')->put($pptxStoragePath, $pptxContents);
        }

        @unlink($result['pptx_path']);

        \App\Models\ProjectGoogleDocument::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'google_file_id' => $result['file_id'],
            'name' => $result['name'],
            'url' => $result['url'],
            'source' => \App\Models\ProjectGoogleDocument::SOURCE_KP_PRESENTATION,
            'project_ai_message_id' => $assistantMessage->id,
        ]);

        return [
            'content' => "Презентация «{$result['name']}» готова.\n\n"
                .'Нажмите **«Открыть в Google Slides»** под этим сообщением, чтобы посмотреть или скачать PPTX. '
                .'Если нужно что-то поправить — напишите, что изменить, и я соберу новую версию.',
            'changes' => null,
        ];
    }

    /**
     * @return array{content: string, changes: array<string, mixed>|null}
     */
    private function buildMeetingResponse(
        Project $project,
        ProjectAiMessage $userMessage,
        ProjectAiMessage $assistantMessage,
        string $message
    ): array {
        $history = $this->historyForApi(
            $project,
            $userMessage->id,
            $assistantMessage->id,
            ProjectAiMessage::THREAD_MEETING
        );

        return [
            'content' => $this->consultationService->generateMeetingContent(
                $project,
                $this->buildUserApiContent($userMessage, $message),
                $history,
                $userMessage->user_id
            ),
            'changes' => null,
        ];
    }

    /**
     * @return array{content: string, changes: array<string, mixed>|null}
     */
    private function buildAuditRecommendationsResponse(
        Project $project,
        ProjectAiMessage $userMessage,
        ProjectAiMessage $assistantMessage,
        string $message
    ): array {
        $history = $this->historyForApi(
            $project,
            $userMessage->id,
            $assistantMessage->id,
            ProjectAiMessage::THREAD_AUDIT_RECOMMENDATIONS
        );

        return [
            'content' => $this->consultationService->generateAuditRecommendationsContent(
                $project,
                $this->buildUserApiContent($userMessage, $message),
                $history,
                $userMessage->user_id
            ),
            'changes' => null,
        ];
    }

    /**
     * @return array{content: string, changes: array<string, mixed>|null}
     */
    private function buildRegularResponse(
        Project $project,
        ProjectAiMessage $userMessage,
        ProjectAiMessage $assistantMessage,
        string $message,
        ?string $mode = null
    ): array {
        $history = $this->historyForApi(
            $project,
            $userMessage->id,
            $assistantMessage->id,
            ProjectAiMessage::THREAD_PROJECT
        );
        $context = $this->contextBuilder->build($project);
        $apiMessages = $this->buildApiMessages(
            $context,
            $history,
            $this->buildUserApiContent($userMessage, $message),
            $mode
        );

        $content = $this->f5AiService->chatCompletion($apiMessages, [
            'user' => F5AiModels::resolveUser($project, $userMessage->user_id),
            'max_tokens' => (int) config('f5ai.max_tokens', 8000),
        ]);
        $parsed = $this->changesParser->parse($content);

        return [
            'content' => $parsed['message'],
            'changes' => WorkStageCostPricing::normalizeChanges($parsed['changes'], $message),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, ProjectAiMessage>
     */
    private function historyForApi(
        Project $project,
        ?int $beforeUserMessageId = null,
        ?int $excludeAssistantId = null,
        string $thread = ProjectAiMessage::THREAD_PROJECT
    ) {
        $query = $project->aiMessages()
            ->where('thread', $thread)
            ->where('status', ProjectAiMessage::STATUS_COMPLETED)
            ->latest('id');

        if ($beforeUserMessageId !== null) {
            $query->where('id', '<', $beforeUserMessageId);
        }

        if ($excludeAssistantId !== null) {
            $query->where('id', '!=', $excludeAssistantId);
        }

        return $query
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  \Illuminate\Support\Collection<int, ProjectAiMessage>  $history
     * @param  string|list<array<string, mixed>>  $message
     * @return array<int, array{role: string, content: string|list<array<string, mixed>>}>
     */
    private function buildApiMessages(array $context, $history, string|array $message, ?string $mode = null): array
    {
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);
        $widgetRules = F5WidgetCatalog::integrationPromptRules();
        $workStageCostRules = WorkStageCostPricing::promptRules();
        $exampleHourCost = WorkStageCostPricing::hourlyRate() * 5;
        $autoTasksRules = $mode === 'auto_tasks'
            ? <<<'RULES'

Специальный режим «Автозадачи»:
- В message кратко сравни 2–3 варианта автоматизации автозадач по ключевым этапам и укажи рекомендуемый.
- Обязательно заполни changes.stages.update (или create для новых этапов): запиши в проект рекомендуемый вариант.
- Базовые поля этапа: action (текст автозадачи менеджеру), deadline (SLA, напр. «2 часа», «1 рабочий день»), stage_type (тип этапа), comment (триггер/условия), kind (normal|win|lose), outcomes (результаты и переходы).
- outcomes: [{"result":"Дозвонился","mode":"move","target_stage_id":123,"label":"опционально"}]. Для новых воронок можно target_sort_order (номер этапа 1..N) или target_stage_name.
- mode outcomes: move|stay|lose|win. Не выдумывай id этапов — бери из контекста.
- НЕ добавляй widgets на этапы и НЕ ставь widget_slug / target_type:widget в outcomes в этом режиме. Виджеты — только если пользователь явно попросил в сообщении.
- В action пиши готовый текст одной рекомендуемой автозадачи (не список «Вариант 1/2»); варианты оставляй в message.
RULES
            : '';

        $systemPrompt = <<<PROMPT
Ты — эксперт по внедрению CRM. Помогаешь настраивать проект: поля сделок/контактов/компаний, интеграции, воронки, этапы воронок (включая автозадачи и SLA) и этапы работ (смета).

Текущее состояние проекта (JSON с id для редактирования; f5_widgets — каталог виджетов команды F5):
{$contextJson}

Отвечай ТОЛЬКО валидным JSON без markdown:
{
  "message": "Ответ пользователю на русском (может быть развёрнутым)",
  "changes": {
    "project": { "fields": { "company_name": "...", "employees_count": 10, "client_tasks": "..." } },
    "skk_fields": {
      "create": [{"entity_type": "contacts", "name": "...", "value_type": "text", "is_required": false, "show_in_card": true, "show_in_list": false, "show_in_filter": false, "options": [], "funnel_stage_id": null, "comment": "..."}],
      "update": [{"id": 1, "name": "...", "value_type": "select", "is_required": true, "options": ["A", "B"], "comment": "..."}],
      "delete": [2]
    },
    "integrations": {
      "create": [{"widget_slug": "chatter", "comment": "сценарий использования"}],
      "update": [{"id": 1, "widget_slug": "telegram-amocrm", "comment": "..."}],
      "delete": []
    },
    "funnels": {
      "create": [{"name": "...", "description": "...", "stages": [{"name": "...", "action": "...", "comment": "...", "stage_type": "...", "deadline": "...", "kind": "normal", "widgets": [], "outcomes": [{"result": "...", "mode": "move", "target_sort_order": 2}]}]}],
      "update": [{"id": 1, "name": "...", "description": "..."}],
      "delete": []
    },
    "stages": {
      "create": [{"funnel_id": 1, "name": "...", "action": "...", "comment": "...", "stage_type": "...", "deadline": "...", "kind": "normal", "widgets": [], "outcomes": []}],
      "update": [{"id": 5, "name": "...", "action": "...", "comment": "...", "stage_type": "...", "deadline": "...", "kind": "normal", "widgets": [], "outcomes": [{"result": "...", "mode": "move", "target_stage_id": 6}]}],
      "delete": []
    },
    "work_stages": {
      "create": [{"name": "Аналитика", "composition": "1) Обследование\\n2) Проектирование", "cost": {$exampleHourCost}, "duration_days": 10}],
      "update": [{"id": 1, "name": "...", "composition": "1) ...\\n2) ...", "cost": {$exampleHourCost}, "duration_days": 15}],
      "delete": []
    }
  }
}

{$widgetRules}
{$autoTasksRules}

{$workStageCostRules}

Правила:
- Не отказывайся от запросов. Делай максимум в рамках возможностей.
- Если нужны только правки — заполни changes. Если только консультация — changes = null.
- Если пользователь просит советы, анализ, рекомендации или аудит — завершай message разделом «Советы по улучшению продаж»: идеи, как усилить продажи клиента через улучшения процессов в amoCRM и/или разработку новых функций под сферу бизнеса (кастомные виджеты под отрасль и сценарии клиента, доработки воронок, отчётности, коммуникаций). Отделяй готовые виджеты F5 из каталога f5_widgets от идей кастомной разработки. На простые запросы правок без совета этот раздел не добавляй.
- Если в сообщении есть блок «Прикреплённый документ» или «Прикреплённое изображение … (распознанный текст OCR)» — опирайся на этот текст как на содержимое файла/скриншота. Не говори, что изображения нет, если OCR-текст передан.
- Для скриншотов воронок/этапов восстанавливай структуру по OCR-тексту (названия этапов, SLA, переходы), даже если бинарное изображение модели недоступно.
- Для новых воронок используй funnels.create со вложенным stages.
- Этапы воронок CRM — ключ "stages". Поле action = автозадача менеджера; deadline = SLA; outcomes = результаты автозадачи и переходы (result, mode, target_stage_id / target_sort_order).
- Виджеты на этапах (widgets) и widget_slug / target_type:widget в outcomes добавляй ТОЛЬКО по явной просьбе пользователя. Базовая автоматизация автозадач и результатов — без виджетов (widgets: [] или поле не трогай; в outcomes без widget_slug).
- Если пользователь явно просит виджеты на этапах: widgets = [{"widget_slug":"chatter","comment":"сценарий"}] (slug из f5_widgets); в outcomes можно widget_slug для запуска виджета результатом.
- Этапы работ / смета внедрения — ключ "work_stages" (name, composition, cost, duration_days — срок в рабочих днях).
- composition этапов работ: каждый пункт состава с новой строки и нумерацией (1) …\\n2) …), не одной строкой через запятую.
- Для изменений используй id из текущего состояния.
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($history as $item) {
            if ($item->role === ProjectAiMessage::ROLE_USER) {
                $messages[] = ['role' => 'user', 'content' => $item->content];
            }

            if ($item->role === ProjectAiMessage::ROLE_ASSISTANT) {
                $messages[] = ['role' => 'assistant', 'content' => $item->content];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }

    public function recoverStaleProcessingMessages(Project $project, ?int $minutes = null): void
    {
        $defaultMinutes = $minutes ?? (int) config('f5ai.stale_minutes', 8);
        $tzMinutes = (int) config('f5ai.stale_minutes_tz_plan', 30);

        $processingMessages = $project->aiMessages()
            ->where('role', ProjectAiMessage::ROLE_ASSISTANT)
            ->where('status', ProjectAiMessage::STATUS_PROCESSING)
            ->orderBy('id')
            ->get();

        foreach ($processingMessages as $assistantMessage) {
            $userMessage = $project->aiMessages()
                ->where('role', ProjectAiMessage::ROLE_USER)
                ->where('id', '<', $assistantMessage->id)
                ->orderByDesc('id')
                ->first();

            $limitMinutes = ($userMessage?->brief_document_id
                || $userMessage?->thread === ProjectAiMessage::THREAD_KP_GENERATION)
                ? $tzMinutes
                : $defaultMinutes;

            if ($assistantMessage->created_at >= now()->subMinutes($limitMinutes)) {
                continue;
            }

            $assistantMessage->update([
                'status' => ProjectAiMessage::STATUS_FAILED,
                'content' => 'Не удалось получить ответ от ИИ.',
                'error_message' => 'Превышено время ожидания. Попробуйте отправить запрос снова.',
            ]);
        }
    }

    private function processingTimeLimit(?string $mode, ?string $thread = null): int
    {
        if ($mode === 'tz_plan') {
            return (int) config('f5ai.timeout_tz_plan', 600) * 3;
        }

        if ($mode === 'kp_work_stages') {
            return (int) config('f5ai.timeout_kp_work_stages', 300) * 3;
        }

        if ($thread === ProjectAiMessage::THREAD_KP_GENERATION) {
            // ИИ-план колоды + Google Slides API + экспорт PPTX.
            return max((int) config('f5ai.timeout', 300), 600);
        }

        return (int) config('f5ai.timeout', 300);
    }
}

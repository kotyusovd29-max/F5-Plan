<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAiMessage;
use App\Support\F5AiModels;

class ProjectAiConsultationService
{
    public function __construct(
        private readonly F5AiService $f5AiService,
        private readonly ProjectAiContextBuilder $contextBuilder
    ) {}

    /**
     * @return array{user: ProjectAiMessage, assistant: ProjectAiMessage}
     */
    public function respond(Project $project, string $mode, string $message): array
    {
        $userMessage = $project->aiMessages()->create([
            'role' => ProjectAiMessage::ROLE_USER,
            'content' => $message,
            'status' => ProjectAiMessage::STATUS_COMPLETED,
        ]);

        $content = $this->generateContent($project, $mode, $message);

        $assistantMessage = $project->aiMessages()->create([
            'role' => ProjectAiMessage::ROLE_ASSISTANT,
            'content' => trim($content),
            'status' => ProjectAiMessage::STATUS_COMPLETED,
            'changes' => null,
        ]);

        return [
            'user' => $userMessage,
            'assistant' => $assistantMessage,
        ];
    }

    /**
     * @param  string|list<array<string, mixed>>  $message
     */
    public function generateContent(
        Project $project,
        string $mode,
        string|array $message,
        ?int $preferredUserId = null
    ): string {
        $context = $this->contextBuilder->build($project);
        $apiMessages = $this->buildApiMessages($context, $mode, $message);

        $content = $this->f5AiService->chatCompletion($apiMessages, [
            'user' => F5AiModels::resolveUser($project, $preferredUserId),
            'max_tokens' => (int) config('f5ai.max_tokens', 8000),
            'temperature' => 0.4,
        ]);

        return trim($content);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProjectAiMessage>  $history
     * @param  string|list<array<string, mixed>>  $message
     */
    public function generateMeetingContent(
        Project $project,
        string|array $message,
        $history,
        ?int $preferredUserId = null
    ): string {
        $apiMessages = $this->buildThreadApiMessages(
            $history,
            $message,
            $this->initialMeetingSystemPrompt()
        );

        $content = $this->f5AiService->chatCompletion($apiMessages, [
            'user' => F5AiModels::resolveUser($project, $preferredUserId),
            'max_tokens' => (int) config('f5ai.max_tokens_meeting', 4500),
            'temperature' => 0.4,
            'timeout' => (int) config('f5ai.timeout', 300),
        ]);

        return trim($content);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProjectAiMessage>  $history
     * @param  string|list<array<string, mixed>>  $message
     */
    public function generateAuditRecommendationsContent(
        Project $project,
        string|array $message,
        $history,
        ?int $preferredUserId = null
    ): string {
        $company = $project->company_name;
        $apiMessages = $this->buildThreadApiMessages(
            $history,
            $message,
            $this->auditRecommendationsSystemPrompt($company)
        );

        $content = $this->f5AiService->chatCompletion($apiMessages, [
            'user' => F5AiModels::resolveUser($project, $preferredUserId),
            'max_tokens' => (int) config('f5ai.max_tokens_meeting', 4500),
            'temperature' => 0.35,
            'timeout' => (int) config('f5ai.timeout', 300),
        ]);

        return trim($content);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProjectAiMessage>  $history
     * @param  string|list<array<string, mixed>>  $message
     * @return array<int, array{role: string, content: string|list<array<string, mixed>>}>
     */
    private function buildThreadApiMessages($history, string|array $message, string $systemPrompt): array
    {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        $recentHistory = $history->slice(-6)->values();

        foreach ($recentHistory as $item) {
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

    private function initialMeetingSystemPrompt(): string
    {
        return <<<'PROMPT'
Ты — CRM-консультант команды F5. Готовишь первичную встречу с клиентом по внедрению amoCRM.

Правила:
- Отвечай на русском в Markdown, без JSON.
- Работай ТОЛЬКО с текстом запроса пользователя (и короткой историей этого чата).
- Если в запросе есть блок «Прикреплённое изображение … (распознанный текст OCR)» — считай этот OCR-текст содержимым скриншота и опирайся на него.
- НЕ используй и НЕ запрашивай данные проекта: воронки, этапы, поля, интеграции, виджеты, план внедрения.
- Не предлагай менять проект — только сценарий встречи.
- Пиши по существу: отвечай именно на запрос пользователя, без лишних разделов.
- Целевой объём: 800–1400 слов. Короче, если вопрос узкий.
- Если в запросе мало деталей — универсальный скелет встречи и уточняющие вопросы.

Если просят полную структуру встречи, используй блоки:
1. Цель
2. Повестка с таймингом (60–90 мин)
3. Вопросы клиенту
4. Что показать
5. Материалы менеджеру
6. Результаты и follow-up
PROMPT;
    }

    private function auditRecommendationsSystemPrompt(string $companyName): string
    {
        return <<<PROMPT
Ты — ведущий консультант по amoCRM команды F5. Готовишь документ «Рекомендации по аудиту» для клиента «{$companyName}».

Контекст:
- Пользователь присылает текст записи встречи / аудита, заметки или уточнения.
- Твой ответ — готовый клиентский документ с рекомендациями по улучшению настройки и процессов в amoCRM.
- Документ можно сохранить в DOCX и отправить клиенту.

Правила:
- Отвечай на русском в Markdown, без JSON и без блока changes.
- Опирайся на текст записи встречи / вложений (включая OCR со скриншотов). Не выдумывай факты, которых нет во входных данных.
- Если данных мало — явно напиши, каких деталей не хватает, и дай осторожные общие рекомендации.
- Пиши языком для клиента: ясно, по делу, без внутреннего жаргона внедрения.
- Не предлагай менять проект в F5 Plan — только рекомендации клиенту.
- Целевой объём: 900–1600 слов. Короче для узкого запроса.

Структура документа (если просят полный документ или прислали запись встречи):
1. **Резюме** — 3–5 предложений
2. **Что уже хорошо**
3. **Рекомендации по улучшению** — с приоритетом (высокий / средний / низкий)
4. **Воронки и этапы**
5. **Поля и карточки**
6. **Автоматизация и роботы**
7. **Интеграции и коммуникации**
8. **Следующие шаги** — конкретный план на 2–4 недели
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  string|list<array<string, mixed>>  $message
     * @return array<int, array{role: string, content: string|list<array<string, mixed>>}>
     */
    private function buildApiMessages(array $context, string $mode, string|array $message): array
    {
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);

        $systemPrompt = match ($mode) {
            'audit' => $this->auditSystemPrompt($contextJson),
            'benchmark_b2b' => $this->benchmarkSystemPrompt($contextJson),
            'project_analysis' => $this->projectAnalysisSystemPrompt($contextJson),
            default => throw new \InvalidArgumentException('Unknown consultation mode: '.$mode),
        };

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message],
        ];
    }

    private function auditSystemPrompt(string $contextJson): string
    {
        return <<<PROMPT
Ты — ведущий аналитик по внедрению amoCRM в F5. Проведи аудит плана внедрения по текущему состоянию проекта.

Текущее состояние проекта (JSON):
{$contextJson}

Ответь на русском в Markdown (без JSON). Структура:
1. **Краткая оценка** (1–2 предложения)
2. **Что уже хорошо** — список
3. **Чего не хватает** — конкретный список с приоритетом (высокий/средний/низкий). Обязательно проверь:
   - достаточно ли полей в сделках, контактах и компаниях (типы, обязательность)
   - описаны ли SLA и действия на этапах воронок
   - есть ли рекомендации по роботам и автоматизации (даже если в проекте нет отдельного раздела — укажи что добавить в комментарии/план)
   - покрыты ли интеграции (телефония, мессенджеры, сайт, аналитика)
   - сквозная аналитика и отчётность
4. **Рекомендации** — 5–8 конкретных следующих шагов
5. **Советы по улучшению продаж** — ПОСЛЕДНИЙ обязательный раздел: идеи, как усилить продажи клиента — улучшения процессов в amoCRM и/или разработка новых функций под сферу бизнеса (кастомные виджеты под отрасль и сценарии клиента, доработки воронок, отчётности, коммуникаций). Отделяй готовые виджеты F5 от идей кастомной разработки.

Будь конкретным: называй реальные поля, этапы и интеграции из проекта или явно пиши, чего нет.
Не предлагай применить изменения автоматически — только анализ и рекомендации.
PROMPT;
    }

    private function projectAnalysisSystemPrompt(string $contextJson): string
    {
        return <<<PROMPT
Ты — эксперт команды F5 по внедрению amoCRM. Проанализируй текущий план проекта и дай практические советы по улучшению.

Текущее состояние проекта (JSON; f5_widgets — каталог виджетов F5):
{$contextJson}

Ответь на русском в Markdown (без JSON). Структура:
1. **Общая оценка** — 2–3 предложения о зрелости плана
2. **Сильные стороны** — что уже хорошо проработано
3. **Советы по улучшению** — 6–10 конкретных рекомендаций с приоритетом (высокий/средний/низкий). Проверь:
   - поля сделок, контактов и компаний (типы, обязательность, аналитика)
   - воронки и этапы (SLA, действия менеджера, роботы)
   - интеграции и виджеты F5 из каталога f5_widgets
   - соответствие задачам клиента из client_tasks
4. **Приоритетный план** — топ-5 шагов на ближайшую итерацию
5. **Советы по улучшению продаж** — ПОСЛЕДНИЙ обязательный раздел: идеи, как усилить продажи клиента — улучшения процессов в amoCRM и/или разработка новых функций под сферу бизнеса (кастомные виджеты под отрасль и сценарии клиента, доработки воронок, отчётности, коммуникаций). Отделяй готовые виджеты F5 из каталога от идей кастомной разработки.

Будь конкретным: ссылайся на поля, этапы и виджеты из проекта или явно указывай пробелы.
Только анализ и советы — не предлагай автоматически применить изменения.
PROMPT;
    }

    private function benchmarkSystemPrompt(string $contextJson): string
    {
        return <<<PROMPT
Ты — эксперт по типовым B2B-внедрениям amoCRM. Сравни текущий план клиента с эталонным B2B-шаблоном F5.

Текущее состояние проекта (JSON):
{$contextJson}

Эталонный B2B-план включает:
- 10–15 структурированных полей (сделки + контакты + компании) с типами
- 1–2 воронки с 5–8 этапами, SLA и действиями менеджера
- поля для источника/UTM/сквозной аналитики
- интеграции: телефония, email, мессенджер, сайт/формы
- описание роботов/автоматизации (хотя бы в рекомендациях)
- роли: менеджер, РОП

Ответь на русском в Markdown (без JSON). Структура:
1. **Оценка схожести с эталоном B2B** — процент 0–100% и одно предложение
2. **Совпадения с эталоном** — список
3. **Отклонения и пробелы** — список по категориям (поля, воронки, интеграции, автоматизация)
4. **Что добавить в первую очередь** — топ-5 пунктов

Будь конкретным. Не генерируй JSON и не предлагай кнопку «применить».
PROMPT;
    }
}

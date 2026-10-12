<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAiMessage;
use App\Models\ProjectSkkField;
use App\Support\F5AiModels;
use App\Support\F5WidgetCatalog;

class ProjectAiTzPlanService
{

    private ?int $preferredUserId = null;

    public function __construct(
        private readonly F5AiService $f5AiService,
        private readonly ProjectAiContextBuilder $contextBuilder,
        private readonly ProjectAiChangesParser $changesParser
    ) {}

    /**
     * @param  callable(string): void|null  $onProgress
     * @return array{report: string, changes: array<string, mixed>|null}
     */
    public function process(
        Project $project,
        string $briefText,
        ?callable $onProgress = null,
        ?int $preferredUserId = null
    ): array {
        $this->preferredUserId = $preferredUserId;
        $onProgress ??= static function (): void {};
        $timeout = (int) config('f5ai.timeout_tz_plan', 600);

        try {
            $onProgress('Анализирую документ ТЗ (шаг 1 из 2)…');
            $report = $this->generatePlanReport($project, $briefText, $timeout);

            $onProgress('Подготавливаю настройки проекта по ТЗ (шаг 2 из 2)…');
            $changes = $this->extractChanges($project, $briefText, $report, $timeout);

            $onProgress('Сверяю воронки с текстом плана…');
            $changes = $this->alignFunnelsWithReport($project, $report, $changes, $timeout);

            $onProgress('Сверяю поля CRM с текстом плана…');
            $changes = $this->alignSkkFieldsWithReport($project, $report, $changes, $timeout);

            return [
                'report' => $report,
                'changes' => $this->changesHaveOperations($changes) ? $changes : null,
            ];
        } finally {
            $this->preferredUserId = null;
        }
    }

    /**
     * Пересобрать JSON-изменения только по уже готовому Markdown-плану (без повторного чтения ТЗ).
     *
     * @return array<string, mixed>|null
     */
    public function rebuildChangesFromReport(
        Project $project,
        string $report,
        ?callable $onProgress = null,
        ?int $preferredUserId = null
    ): ?array {
        $this->preferredUserId = $preferredUserId;
        $onProgress ??= static function (): void {};
        $timeout = (int) config('f5ai.timeout_tz_plan', 600);
        $report = trim($report);

        if ($report === '') {
            $this->preferredUserId = null;

            return null;
        }

        try {
            $onProgress('Собираю изменения по готовому плану…');
            $changes = $this->extractChanges($project, '', $report, $timeout);

            $onProgress('Сверяю воронки с текстом плана…');
            $changes = $this->alignFunnelsWithReport($project, $report, $changes, $timeout);

            $onProgress('Сверяю поля CRM с текстом плана…');
            $changes = $this->alignSkkFieldsWithReport($project, $report, $changes, $timeout);

            return $this->changesHaveOperations($changes) ? $changes : null;
        } finally {
            $this->preferredUserId = null;
        }
    }

    private function aiUser(Project $project): ?\App\Models\User
    {
        return F5AiModels::resolveUser($project, $this->preferredUserId);
    }

    private function generatePlanReport(Project $project, string $briefText, int $timeout): string
    {
        $context = $this->contextBuilder->build($project);
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);
        $funnelRules = $this->funnelStageRules();

        $systemPrompt = <<<PROMPT
Ты — CRM-архитектор команды F5. По техническому заданию клиента подготовь практический план внедрения amoCRM.

Текущее состояние проекта в планировщике (JSON; f5_widgets — каталог виджетов F5):
{$contextJson}

Ответь на русском в Markdown (без JSON). Структура:
1. **Резюме ТЗ**
2. **Цели внедрения**
3. **Поля CRM**
4. **Воронки и этапы**
5. **Интеграции и виджеты F5**
6. **Автоматизация и роботы**
7. **Этапы внедрения**
8. **Риски и допущения**

ОБЯЗАТЕЛЬНЫЙ формат раздела «Воронки и этапы» (его потом парсят программы):
## Воронки и этапы
### Воронка: <название>
1. <название этапа> — <действие менеджера>
2. <название этапа> — <действие менеджера>

Несколько воронок — несколько блоков `### Воронка: ...`.
Этапы бери из ТЗ, не из шаблона F5.

{$funnelRules}

Опирайся на ТЗ. Без «воды».
PROMPT;

        return $this->f5AiService->chatCompletion([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Техническое задание клиента:\n\n{$briefText}"],
        ], [
            'user' => $this->aiUser($project),
            'large' => true,
            'max_tokens' => (int) config('f5ai.max_tokens_report', 12000),
            'temperature' => 0.2,
            'timeout' => $timeout,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractChanges(Project $project, string $briefText, string $report, int $timeout): ?array
    {
        $attempts = [
            ['mode' => 'from_report', 'compact' => false],
            ['mode' => 'from_report', 'compact' => true],
            ['mode' => 'from_brief_and_report', 'compact' => false],
            ['mode' => 'from_brief_and_report', 'compact' => true],
        ];

        $best = null;
        $bestScore = -1;

        foreach ($attempts as $attempt) {
            try {
                $changes = $this->requestChanges(
                    $project,
                    $briefText,
                    $report,
                    $attempt['mode'],
                    $attempt['compact'],
                    $timeout
                );

                if (! $this->changesHaveOperations($changes)) {
                    continue;
                }

                $score = $this->scoreChangesCompleteness($changes, $report);

                if ($score > $bestScore) {
                    $best = $changes;
                    $bestScore = $score;
                }

                if ($this->changesLookComplete($changes, $report)) {
                    return $changes;
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>|null  $changes
     */
    private function scoreChangesCompleteness(?array $changes, string $report): int
    {
        if ($changes === null) {
            return -1;
        }

        $score = 0;
        $score += min(3, count($changes['funnels']['create'] ?? []));
        $score += min(5, count($changes['skk_fields']['create'] ?? [])) * 2;
        $score += min(3, count($changes['integrations']['create'] ?? []));

        if ($this->reportMentionsFields($report) && count($changes['skk_fields']['create'] ?? []) === 0) {
            $score -= 10;
        }

        if ($this->reportMentionsFunnels($report) && count($changes['funnels']['create'] ?? []) === 0) {
            $score -= 5;
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>|null  $changes
     */
    private function changesLookComplete(?array $changes, string $report): bool
    {
        if (! $this->changesHaveOperations($changes)) {
            return false;
        }

        if ($this->reportMentionsFields($report) && count($changes['skk_fields']['create'] ?? []) === 0) {
            return false;
        }

        if ($this->reportMentionsFunnels($report) && count($changes['funnels']['create'] ?? []) === 0) {
            return false;
        }

        return true;
    }

    private function reportMentionsFields(string $report): bool
    {
        return (bool) preg_match('/поля\s+crm|^#{1,4}\s+[^\n]*\bполя\b/imu', $report);
    }

    private function reportMentionsFunnels(string $report): bool
    {
        return (bool) preg_match('/воронки\s+и\s+этапы|^#{1,4}\s+[^\n]*\bворонк/imu', $report);
    }

    /**
     * @param  array<string, mixed>|null  $changes
     * @return array<string, mixed>|null
     */
    private function alignFunnelsWithReport(
        Project $project,
        string $report,
        ?array $changes,
        int $timeout
    ): ?array {
        $parsedFunnels = $this->parseFunnelsFromMarkdown($report);

        if ($parsedFunnels === []) {
            try {
                $parsedFunnels = $this->requestFunnelsFromReport($project, $report, $timeout);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        if ($parsedFunnels === []) {
            return $changes;
        }

        if ($changes === null) {
            $changes = $this->emptyChangesSkeleton();
        }

        $existingDeletes = is_array($changes['funnels']['delete'] ?? null)
            ? $changes['funnels']['delete']
            : [];

        if ($existingDeletes === [] && $project->funnels()->exists()) {
            $existingDeletes = $project->funnels()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $changes['funnels'] = [
            'create' => $parsedFunnels,
            'update' => [],
            'delete' => $existingDeletes,
        ];

        // Этапы уже вложены в funnels.create — отдельные stages.create не нужны.
        $changes['stages'] = [
            'create' => [],
            'update' => [],
            'delete' => is_array($changes['stages']['delete'] ?? null) ? $changes['stages']['delete'] : [],
        ];

        return $changes;
    }

    /**
     * @param  array<string, mixed>|null  $changes
     * @return array<string, mixed>|null
     */
    private function alignSkkFieldsWithReport(
        Project $project,
        string $report,
        ?array $changes,
        int $timeout
    ): ?array {
        $existingCreates = is_array($changes['skk_fields']['create'] ?? null)
            ? $changes['skk_fields']['create']
            : [];

        if ($existingCreates !== []) {
            return $changes;
        }

        $parsedFields = $this->parseSkkFieldsFromMarkdown($report);

        if ($parsedFields === []) {
            try {
                $parsedFields = $this->requestSkkFieldsFromReport($project, $report, $timeout);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        if ($parsedFields === []) {
            return $changes;
        }

        if ($changes === null) {
            $changes = $this->emptyChangesSkeleton();
        }

        $normalized = $this->changesParser->parse(json_encode([
            'message' => 'Поля CRM по плану',
            'changes' => [
                'skk_fields' => [
                    'create' => $parsedFields,
                    'update' => [],
                    'delete' => [],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

        $creates = $normalized['changes']['skk_fields']['create'] ?? [];

        if (! is_array($creates) || $creates === []) {
            return $changes;
        }

        $existingDeletes = is_array($changes['skk_fields']['delete'] ?? null)
            ? $changes['skk_fields']['delete']
            : [];

        if ($existingDeletes === [] && $project->skkFields()->exists()) {
            $existingDeletes = $project->skkFields()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $changes['skk_fields'] = [
            'create' => array_values($creates),
            'update' => [],
            'delete' => $existingDeletes,
        ];

        return $changes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseSkkFieldsFromMarkdown(string $report): array
    {
        $section = $this->extractFieldsSection($report);

        if ($section === '') {
            return [];
        }

        $fields = [];
        $entity = ProjectSkkField::TYPE_DEALS;
        $pendingSelectIndex = null;
        $lines = preg_split('/\R/u', $section) ?: [];

        foreach ($lines as $line) {
            $raw = rtrim($line);
            $line = trim($raw);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^#{1,4}\s+(.+)$/u', $line, $headingMatch)) {
                $pendingSelectIndex = null;
                $title = mb_strtolower(trim(preg_replace('/\*+/u', '', $headingMatch[1]) ?? $headingMatch[1]));

                if (str_contains($title, 'контакт')) {
                    $entity = ProjectSkkField::TYPE_CONTACTS;
                } elseif (str_contains($title, 'компани')) {
                    $entity = ProjectSkkField::TYPE_COMPANIES;
                } elseif (
                    str_contains($title, 'сделк')
                    || str_contains($title, 'воронк')
                    || str_contains($title, 'маркер')
                ) {
                    $entity = ProjectSkkField::TYPE_DEALS;
                }

                continue;
            }

            // Опции списка: вложенные маркеры без «— тип».
            if (
                $pendingSelectIndex !== null
                && preg_match('/^[-*•]\s+(.+)$/u', $line, $optionMatch)
                && ! preg_match('/\s+[—–\-]\s+/u', $line)
            ) {
                $option = trim(preg_replace('/\*+/u', '', $optionMatch[1]) ?? $optionMatch[1], " \t\"'«»");

                if ($option !== '' && mb_strlen($option) <= 80) {
                    $fields[$pendingSelectIndex]['options'][] = $option;
                }

                continue;
            }

            if (! preg_match('/^[-*•]\s+(.+)$/u', $line, $itemMatch)) {
                $pendingSelectIndex = null;
                continue;
            }

            $body = trim($itemMatch[1]);

            if (! preg_match('/^(.+?)\s+[—–\-]\s+(.+)$/u', $body, $parts)) {
                $pendingSelectIndex = null;
                continue;
            }

            $name = trim(preg_replace('/\*+/u', '', $parts[1]) ?? $parts[1], " \t\"'«»");
            $hint = trim($parts[2]);

            if ($name === '' || mb_strlen($name) > 120) {
                $pendingSelectIndex = null;
                continue;
            }

            // Пропускаем действия менеджера / этапы, попавшие в раздел полей.
            if ($this->looksLikeManagerActionHint($hint) && ! $this->looksLikeFieldTypeHint($hint)) {
                $pendingSelectIndex = null;
                continue;
            }

            $valueType = $this->inferFieldValueType($hint);
            $isRequired = (bool) preg_match('/обязательн/iu', $hint);
            $comment = $this->sanitizeUtf8(trim($hint, " \t:"));

            $field = [
                'name' => $this->sanitizeUtf8($name),
                'entity_type' => $entity,
                'value_type' => $valueType,
                'comment' => $comment,
                'is_required' => $isRequired,
                'show_in_card' => true,
                'show_in_list' => true,
                'show_in_filter' => in_array($valueType, [ProjectSkkField::VALUE_SELECT, ProjectSkkField::VALUE_TOGGLE], true),
                'options' => [],
            ];

            $fields[] = $field;
            $index = array_key_last($fields);

            if (
                in_array($valueType, [ProjectSkkField::VALUE_SELECT, ProjectSkkField::VALUE_MULTISELECT], true)
                || str_ends_with($hint, ':')
                || preg_match('/\bсписок\b/iu', $hint)
            ) {
                $pendingSelectIndex = $index;
            } else {
                $pendingSelectIndex = null;
            }
        }

        return array_values(array_map(static function (array $field): array {
            if (($field['options'] ?? []) === []) {
                unset($field['options']);
            }

            return $field;
        }, $fields));
    }

    private function looksLikeFieldTypeHint(string $hint): bool
    {
        return (bool) preg_match(
            '/\b(дата|телефон|текст|строка|число|деньги|список|мультисписок|чекбокс|переключатель|расч[её]тн)/iu',
            $hint
        );
    }

    private function looksLikeManagerActionHint(string $hint): bool
    {
        return (bool) preg_match('/\bменеджер\b/iu', $hint);
    }

    private function inferFieldValueType(string $hint): string
    {
        $hintLower = mb_strtolower($hint);

        if (preg_match('/мультисписок|несколько значен/u', $hintLower)) {
            return ProjectSkkField::VALUE_MULTISELECT;
        }

        if (preg_match('/\bсписок\b|select/u', $hintLower)) {
            return ProjectSkkField::VALUE_SELECT;
        }

        if (preg_match('/чекбокс|переключатель|toggle|да\/нет/u', $hintLower)) {
            return ProjectSkkField::VALUE_TOGGLE;
        }

        if (preg_match('/\bдата\b|срок/u', $hintLower)) {
            return ProjectSkkField::VALUE_DATE;
        }

        if (preg_match('/число|деньги|бюджет|маржа|скидк|цен[аы]|прибыл|%|процент/u', $hintLower)) {
            return ProjectSkkField::VALUE_NUMBER;
        }

        return ProjectSkkField::VALUE_TEXT;
    }

    private function extractFieldsSection(string $report): string
    {
        if (! preg_match_all('/^(#{1,4})\s+(.+)$/mu', $report, $matches, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $bestStart = null;
        $bestEnd = null;
        $bestScore = -1;
        $bestLength = -1;

        foreach ($matches[0] as $index => $full) {
            $level = strlen($matches[1][$index][0]);
            $titlePlain = mb_strtolower(
                trim(preg_replace('/\*+/u', '', $matches[2][$index][0]) ?? $matches[2][$index][0])
            );
            $start = $full[1];

            $score = 0;

            if (preg_match('/поля\s+crm/u', $titlePlain) || preg_match('/^\d+[.)]?\s*поля\s+crm/u', $titlePlain)) {
                $score = 100;
            } elseif (preg_match('/^поля\b/u', $titlePlain) && ! str_contains($titlePlain, 'воронк')) {
                $score = 80;
            }

            if (
                str_contains($titlePlain, 'воронки и этап')
                || str_contains($titlePlain, 'интеграц')
                || str_contains($titlePlain, 'автоматизац')
                || str_contains($titlePlain, 'риск')
            ) {
                $score = 0;
            }

            if ($score <= 0) {
                continue;
            }

            $end = strlen($report);

            for ($next = $index + 1, $total = count($matches[0]); $next < $total; $next++) {
                $nextLevel = strlen($matches[1][$next][0]);

                if ($nextLevel <= $level) {
                    $end = $matches[0][$next][1];
                    break;
                }
            }

            $length = $end - $start;

            if ($score > $bestScore || ($score === $bestScore && $length > $bestLength)) {
                $bestScore = $score;
                $bestStart = $start;
                $bestEnd = $end;
                $bestLength = $length;
            }
        }

        if ($bestStart === null || $bestEnd === null) {
            return '';
        }

        return trim(substr($report, $bestStart, $bestEnd - $bestStart));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requestSkkFieldsFromReport(Project $project, string $report, int $timeout): array
    {
        $section = $this->extractFieldsSection($report) ?: $report;

        $content = $this->f5AiService->chatCompletion([
            ['role' => 'system', 'content' => <<<'PROMPT'
Извлеки поля CRM ТОЛЬКО из переданного Markdown-плана.
Ответ — один JSON-объект без markdown:
{"fields":[{"name":"...","entity_type":"deals|contacts|companies","value_type":"text|number|date|select|multiselect|toggle","comment":"...","is_required":false,"options":[]}]}

Правила:
- entity_type: deals (сделки), contacts (контакты), companies (компании).
- Не добавляй этапы воронок как поля.
- Для списков заполни options.
- Если полей нет — {"fields":[]}.
PROMPT],
            ['role' => 'user', 'content' => $section],
        ], [
            'user' => $this->aiUser($project),
            'max_tokens' => (int) config('f5ai.max_tokens_changes', 0),
            'temperature' => 0,
            'timeout' => min($timeout, 180),
        ]);

        $fields = $this->decodeFieldsPayload($content);

        if ($fields === []) {
            return [];
        }

        $parsed = $this->changesParser->parse(json_encode([
            'message' => 'Поля CRM по плану',
            'changes' => [
                'skk_fields' => [
                    'create' => $fields,
                    'update' => [],
                    'delete' => [],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE));

        $created = $parsed['changes']['skk_fields']['create'] ?? [];

        return is_array($created) ? array_values($created) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeFieldsPayload(string $content): array
    {
        $content = trim($content);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $content, $matches)) {
            $content = trim($matches[1]);
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                $decoded = json_decode($matches[0], true);
            }
        }

        if (! is_array($decoded)) {
            return [];
        }

        if (isset($decoded['fields']) && is_array($decoded['fields'])) {
            return array_values($decoded['fields']);
        }

        if (isset($decoded['skk_fields']['create']) && is_array($decoded['skk_fields']['create'])) {
            return array_values($decoded['skk_fields']['create']);
        }

        if (array_is_list($decoded)) {
            return $decoded;
        }

        return [];
    }

    /**
     * @return list<array{name: string, description: string, stages: list<array{name: string, action: string, comment: string}>}>
     */
    private function parseFunnelsFromMarkdown(string $report): array
    {
        $section = $this->extractFunnelsSection($report);

        if ($section === '') {
            return [];
        }

        $funnels = [];

        if (preg_match_all(
            '/^#{3,4}\s*(?:воронка\s*(?:\d+\s*)?[:.—-]\s*)?(.+?)\s*$/ium',
            $section,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            foreach ($matches[0] as $index => $fullMatch) {
                $name = trim($matches[1][$index][0]);

                if ($this->isSectionHeadingNotFunnel($name)) {
                    continue;
                }

                $start = $fullMatch[1];
                $end = $matches[0][$index + 1][1] ?? strlen($section);
                $chunk = substr($section, $start, $end - $start);
                $stages = $this->parseStagesFromChunk($chunk);

                if ($stages === []) {
                    continue;
                }

                $funnels[] = [
                    'name' => $this->sanitizeUtf8($this->cleanFunnelName($name)),
                    'description' => 'По тексту плана ТЗ',
                    'stages' => $stages,
                ];
            }
        }

        if ($funnels !== []) {
            return $funnels;
        }

        // В разделе есть только нумерованный список этапов без ### Воронка.
        $stages = $this->parseStagesFromChunk($section);

        if (count($stages) < 2) {
            return [];
        }

        return [[
            'name' => 'Основная воронка',
            'description' => 'По тексту плана ТЗ',
            'stages' => $stages,
        ]];
    }

    private function isSectionHeadingNotFunnel(string $title): bool
    {
        $titleLower = mb_strtolower(preg_replace('/\*+/u', '', $title) ?? $title);

        return str_contains($titleLower, 'воронки и этап')
            || str_contains($titleLower, 'этапы внедрения')
            || str_contains($titleLower, 'автоматизац')
            || str_contains($titleLower, 'интеграц')
            || str_contains($titleLower, 'риски')
            || str_contains($titleLower, 'поля')
            || str_contains($titleLower, 'маркер')
            || str_contains($titleLower, 'резюме')
            || str_contains($titleLower, 'цели')
            || str_contains($titleLower, 'виджет');
    }

    /**
     * @return list<array{name: string, action: string, comment: string}>
     */
    private function parseStagesFromChunk(string $chunk): array
    {
        $numbered = [];
        $bullets = [];
        $lines = preg_split('/\R/u', $chunk) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || preg_match('/^#{1,4}\s/', $line)) {
                continue;
            }

            // Блоки полей внутри описания воронки пропускаем.
            if (preg_match('/^(обязательные\s+поля|основные\s+поля|поля\s*:)/iu', $line)) {
                break;
            }

            $isNumbered = (bool) preg_match('/^\d+[.)]\s+(.+)$/u', $line, $match);
            $isBullet = ! $isNumbered && (bool) preg_match('/^[-*•]\s+(.+)$/u', $line, $match);

            if (! $isNumbered && ! $isBullet) {
                continue;
            }

            $body = trim($match[1]);
            $name = $body;
            $action = '';

            if (preg_match('/^(.+?)\s+[—–\-]\s+(.+)$/u', $body, $parts)) {
                $name = trim($parts[1]);
                $action = trim($parts[2]);
            }

            $name = trim(preg_replace('/\*+/u', '', $name) ?? $name, " \t\n\r\0\x0B\"'«»");

            if ($name === '' || mb_strlen($name) > 120) {
                continue;
            }

            if ($this->looksLikeFieldStage($name, $action)) {
                continue;
            }

            $stage = [
                'name' => $this->sanitizeUtf8($name),
                'action' => $this->sanitizeUtf8($action),
                'comment' => '',
            ];

            if ($isNumbered) {
                $numbered[] = $stage;
            } else {
                $bullets[] = $stage;
            }
        }

        // Нумерованный список этапов предпочтительнее маркеров (часто это поля/опции).
        return $numbered !== [] ? $numbered : $bullets;
    }

    private function looksLikeFieldStage(string $name, string $action): bool
    {
        $actionLower = mb_strtolower($action);

        if (preg_match(
            '/^(дата|телефон|текст|строка|число|деньги|список|чекбокс|переключатель|мультисписок|расч[её]тное)/u',
            $actionLower
        )) {
            return true;
        }

        if (str_contains($actionLower, 'автозаполнение')
            || str_contains($actionLower, 'только для чтения')
            || str_contains($actionLower, 'обязательно при')
            || str_contains($actionLower, 'обязательно после')
            || str_contains($actionLower, 'обязательно кроме')
        ) {
            return true;
        }

        $fieldishNames = [
            'дата обращения',
            'номер телефона',
            'канал обращения',
            'комментарий менеджера',
            'модель техники',
            'себестоимость',
            'желаемая скидка',
            'итоговая цена',
            'итоговая прибыль',
            'причина отказа',
        ];

        return in_array(mb_strtolower($name), $fieldishNames, true);
    }

    private function extractFunnelsSection(string $report): string
    {
        if (! preg_match_all('/^(#{1,4})\s+(.+)$/mu', $report, $matches, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $bestStart = null;
        $bestEnd = null;
        $bestScore = -1;
        $bestLength = -1;

        foreach ($matches[0] as $index => $full) {
            $level = strlen($matches[1][$index][0]);
            $titlePlain = mb_strtolower(
                trim(preg_replace('/\*+/u', '', $matches[2][$index][0]) ?? $matches[2][$index][0])
            );
            $start = $full[1];

            $score = 0;

            if (preg_match('/воронки\s+и\s+этапы/u', $titlePlain)) {
                $score = 100;
            } elseif (preg_match('/^доп[.у]*\s*\d*[.):]?\s*воронки\b/u', $titlePlain)) {
                $score = 0;
            } elseif (preg_match('/^воронки\b/u', $titlePlain) && ! str_contains($titlePlain, 'поля')) {
                $score = 80;
            } elseif (
                str_contains($titlePlain, 'воронк')
                && str_contains($titlePlain, 'этап')
                && ! str_contains($titlePlain, 'поля')
            ) {
                $score = 70;
            }

            if (
                str_contains($titlePlain, 'поля')
                || str_contains($titlePlain, 'маркер')
                || str_contains($titlePlain, 'интеграц')
                || str_contains($titlePlain, 'виджет')
            ) {
                $score = 0;
            }

            if ($score <= 0) {
                continue;
            }

            $end = strlen($report);

            for ($next = $index + 1, $total = count($matches[0]); $next < $total; $next++) {
                $nextLevel = strlen($matches[1][$next][0]);

                if ($nextLevel <= $level) {
                    $end = $matches[0][$next][1];
                    break;
                }
            }

            $length = $end - $start;

            if ($score > $bestScore || ($score === $bestScore && $length > $bestLength)) {
                $bestScore = $score;
                $bestStart = $start;
                $bestEnd = $end;
                $bestLength = $length;
            }
        }

        if ($bestStart === null || $bestEnd === null) {
            return '';
        }

        return trim(substr($report, $bestStart, $bestEnd - $bestStart));
    }

    private function cleanFunnelName(string $name): string
    {
        $name = preg_replace('/^воронка\s*(?:\d+\s*)?[:.—-]?\s*/iu', '', trim($name)) ?? $name;
        $name = trim(preg_replace('/\*+/u', '', $name) ?? $name, " \t\n\r\0\x0B\"'«»");

        return $name !== '' ? $name : 'Воронка';
    }

    /**
     * @return list<array{name: string, description: string, stages: list<array{name: string, action: string, comment: string}>}>
     */
    private function requestFunnelsFromReport(Project $project, string $report, int $timeout): array
    {
        $section = $this->extractFunnelsSection($report) ?: $report;

        $content = $this->f5AiService->chatCompletion([
            ['role' => 'system', 'content' => <<<'PROMPT'
Извлеки воронки и этапы ТОЛЬКО из переданного Markdown-плана.
Ответ — один JSON-объект без markdown:
{"funnels":[{"name":"...","description":"...","stages":[{"name":"...","action":"...","comment":""}]}]}

Правила:
- Не добавляй этапы, которых нет в тексте.
- Сохраняй порядок этапов.
- Если воронок нет — {"funnels":[]}.
PROMPT],
            ['role' => 'user', 'content' => $section],
        ], [
            'user' => $this->aiUser($project),
            'max_tokens' => (int) config('f5ai.max_tokens_changes', 0),
            'temperature' => 0,
            'timeout' => min($timeout, 180),
        ]);

        $funnels = $this->decodeFunnelsPayload($content);

        if ($funnels === []) {
            return [];
        }

        $parsed = $this->changesParser->parse(json_encode([
            'message' => 'Воронки по плану',
            'changes' => [
                'funnels' => [
                    'create' => $funnels,
                    'update' => [],
                    'delete' => [],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE));

        $created = $parsed['changes']['funnels']['create'] ?? [];

        return is_array($created) ? array_values($created) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeFunnelsPayload(string $content): array
    {
        $content = trim($content);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $content, $matches)) {
            $content = trim($matches[1]);
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                $decoded = json_decode($matches[0], true);
            }
        }

        if (! is_array($decoded)) {
            return [];
        }

        if (isset($decoded['funnels']) && is_array($decoded['funnels'])) {
            return array_values($decoded['funnels']);
        }

        if (array_is_list($decoded)) {
            return $decoded;
        }

        return [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestChanges(
        Project $project,
        string $briefText,
        string $report,
        string $mode,
        bool $compact,
        int $timeout
    ): ?array {
        $context = $this->contextBuilder->build($project);
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);
        $widgetRules = F5WidgetCatalog::integrationPromptRules();
        $funnelRules = $this->funnelStageRules();

        $planFocus = $this->extractPlanFocus($report);
        $reportForChanges = $planFocus !== '' ? $planFocus : $report;

        $systemPrompt = $this->changesSystemPrompt($contextJson, $widgetRules, $funnelRules, $compact);

        if ($mode === 'from_report') {
            $userPrompt = <<<PROMPT
Преобразуй подготовленный Markdown-план ниже в JSON-изменения для загрузки в планировщик.

ВАЖНО:
- Не пересказывай план заново — перенеси из него поля, воронки (со всеми этапами), интеграции.
- Каждая воронка и каждый этап из раздела «Воронки и этапы» обязательны в funnels.create[].stages[].
- Не оставляй changes пустым и не ставь changes: null.

Подготовленный план:
{$reportForChanges}
PROMPT;
        } else {
            $userPrompt = <<<PROMPT
По ТЗ и Markdown-плану сформируй JSON-изменения для планировщика.
Приоритет у Markdown-плана: воронки/этапы/поля бери из него. ТЗ — только для уточнений.

ТЗ клиента:
{$briefText}

Подготовленный план:
{$reportForChanges}
PROMPT;
        }

        $content = $this->f5AiService->chatCompletion([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ], [
            'user' => $this->aiUser($project),
            'large' => ! $compact,
            'max_tokens' => (int) config('f5ai.max_tokens_changes', 10000),
            'temperature' => $compact ? 0.05 : 0.1,
            'timeout' => $timeout,
        ]);

        return $this->changesParser->parse(
            $content,
            'ИИ вернул ответ в неверном формате. Попробуйте переформулировать запрос по документу ТЗ.'
        )['changes'];
    }

    private function changesSystemPrompt(
        string $contextJson,
        string $widgetRules,
        string $funnelRules,
        bool $compact
    ): string {
        $volumeRule = $compact
            ? 'Верни компактный, но полный набор: минимум поля + все воронки/этапы из плана + ключевые интеграции.'
            : 'Верни максимально полный набор изменений по плану: все перечисленные поля, воронки с этапами и интеграции.';

        return <<<PROMPT
Ты — CRM-архитектор. Твоя задача — превратить готовый план внедрения в JSON для CRM-планировщика.

Текущее состояние проекта (id для update/delete; f5_widgets — каталог виджетов F5):
{$contextJson}

КРИТИЧЕСКИ ВАЖНО:
- Ответ — ТОЛЬКО один JSON-объект. Без markdown, без ```json, без текста до или после JSON.
- Поле changes обязательно и НЕ может быть null.
- Если в плане есть воронки — они обязаны попасть в changes.funnels.create (со stages).
- Если не уверен в id — используй только create.

Формат:
{
  "message": "Краткое резюме",
  "changes": {
    "project": { "fields": { "client_tasks": "краткое резюме из ТЗ" } },
    "skk_fields": { "create": [], "update": [], "delete": [] },
    "integrations": { "create": [], "update": [], "delete": [] },
    "funnels": { "create": [{"name": "...", "description": "...", "stages": [{"name": "...", "action": "...", "comment": "..."}]}], "update": [], "delete": [] },
    "stages": { "create": [], "update": [], "delete": [] }
  }
}

{$widgetRules}

{$funnelRules}

{$volumeRule}
PROMPT;
    }

    private function funnelStageRules(): string
    {
        return <<<'TEXT'
Правила воронок и этапов (строго):
1. Источник истины — текст плана/ТЗ.
2. Названия этапов почти дословно, порядок сохранить.
3. Не добавляй шаблон F5 (Новый лид / Квалификация / КП / Счёт / Успешно), если его нет в тексте.
4. Не смешивай разные процессы в одну воронку.
5. Автозадачи (action) и outcomes — базово без виджетов на этапах: widgets не заполняй, в outcomes не ставь widget_slug. Виджеты на этапах/результатах — только если это явно есть в плане/ТЗ.
TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyChangesSkeleton(): array
    {
        return [
            'project' => ['fields' => []],
            'skk_fields' => ['create' => [], 'update' => [], 'delete' => []],
            'integrations' => ['create' => [], 'update' => [], 'delete' => []],
            'funnels' => ['create' => [], 'update' => [], 'delete' => []],
            'stages' => ['create' => [], 'update' => [], 'delete' => []],
        ];
    }

    private function extractPlanFocus(string $report): string
    {
        $report = trim($report);

        if ($report === '') {
            return '';
        }

        $chunks = [];

        if (preg_match_all(
            '/(#{1,4}\s*[^\n]*(поля|скк|воронк|этап|интеграц|виджет)[^\n]*\n[\s\S]*?)(?=\n#{1,4}\s|\z)/iu',
            $report,
            $matches
        )) {
            foreach ($matches[1] as $chunk) {
                $chunk = trim($chunk);

                if ($chunk !== '') {
                    $chunks[] = $chunk;
                }
            }
        }

        return $chunks === [] ? $report : implode("\n\n", $chunks);
    }

    /**
     * @param  array<string, mixed>|null  $changes
     */
    private function changesHaveOperations(?array $changes): bool
    {
        if ($changes === null) {
            return false;
        }

        $probe = new ProjectAiMessage([
            'role' => ProjectAiMessage::ROLE_ASSISTANT,
            'status' => ProjectAiMessage::STATUS_COMPLETED,
            'changes' => $changes,
        ]);

        return $probe->changesHasOperations();
    }

    private function excerpt(string $text, int $limit): string
    {
        $text = trim($text);

        if ($text === '' || mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit)."\n\n[…текст обрезан по длине…]";
    }

    private function sanitizeUtf8(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return trim($value);
        }

        $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return trim(is_string($cleaned) ? $cleaned : '');
    }
}

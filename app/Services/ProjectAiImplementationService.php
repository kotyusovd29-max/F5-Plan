<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectSkkField;

class ProjectAiImplementationService
{
    public function __construct(
        private readonly F5AiService $f5AiService,
        private readonly ProjectAiContextBuilder $contextBuilder,
        private readonly ProjectAiChangesParser $changesParser
    ) {}

    public function isImplementationBrief(string $message): bool
    {
        if (mb_strlen($message) >= 1500) {
            return true;
        }

        return (bool) preg_match(
            '/(amocrm|amo\s*crm|внедрени|кейс|воронк|сквозн|автоматизац|интеграц)/ui',
            $message
        );
    }

    /**
     * @return array{report: string, changes: array<string, mixed>|null}
     */
    public function process(Project $project, string $message): array
    {
        $report = $this->generateReport($message);
        $changes = $this->extractChanges($project, $message, $report);

        return [
            'report' => $report,
            'changes' => $changes,
        ];
    }

    private function generateReport(string $message): string
    {
        $systemPrompt = <<<'PROMPT'
Ты — CRM-интегратор с опытом внедрения amoCRM. Подготовь подробный практический кейс внедрения по запросу клиента.

Требования:
- Пиши на русском, структурированно по разделам из запроса (нумерация, заголовки).
- Максимум конкретики: реальные названия этапов, полей, роботов, виджетов, сценарии.
- Минимум абстракций и «воды».
- Опирайся на типовые практики amoCRM и решения команды F5 (cmdf5.ru/cases, cmdf5.ru/widjety-amocrm).
- Если в запросе указан сайт компании — учти специфику бизнеса.
- НЕ отказывайся от большого объёма. НЕ пиши, что запрос слишком большой.
- Ответ — только текст кейса в Markdown (без JSON).
PROMPT;

        return $this->f5AiService->chatCompletion([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message],
        ], [
            'model' => config('f5ai.model_large'),
            'max_tokens' => (int) config('f5ai.max_tokens_report', 16000),
            'temperature' => 0.5,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractChanges(Project $project, string $message, string $report): ?array
    {
        $context = $this->contextBuilder->build($project);
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);
        $reportExcerpt = mb_substr($report, 0, 14000);

        $systemPrompt = <<<PROMPT
Ты — CRM-архитектор. По запросу клиента и подготовленному кейсу сформируй изменения для проекта в CRM-планировщике.

Текущее состояние проекта (id для update/delete):
{$contextJson}

Ответь ТОЛЬКО валидным JSON без markdown:
{
  "message": "Краткое резюме: что будет загружено в проект",
  "changes": {
    "project": { "fields": { "company_name": "...", "employees_count": null, "client_tasks": "краткое резюме задач" } },
    "skk_fields": {
      "create": [{"entity_type": "deals|contacts|companies", "name": "...", "comment": "тип поля и зачем"}],
      "update": [],
      "delete": []
    },
    "integrations": {
      "create": [{"name": "...", "comment": "какую проблему решает и сценарий"}],
      "update": [],
      "delete": []
    },
    "funnels": {
      "create": [{
        "name": "Название воронки",
        "description": "Цель воронки",
        "stages": [
          {"name": "Этап", "action": "Что делает менеджер", "comment": "Триггер перехода / SLA"}
        ]
      }],
      "update": [],
      "delete": []
    },
    "stages": { "create": [], "update": [], "delete": [] }
  }
}

Правила:
- Заполни проект по кейсу: минимум 8–12 полей (deals+contacts+companies), 2–4 воронки с детальными этапами, 5–10 интеграций/виджетов.
- Новые воронки создавай через funnels.create со вложенным массивом stages (не используй funnel_id для новых воронок).
- Для существующих элементов используй update/delete по id. Для полной перестройки — delete старых + create новых.
- comment у полей — тип и назначение; action у этапа — обязательные действия менеджера.
- Не выдумывай id. Если проект пустой — только create.
- Не отказывайся. Верни максимально полный набор изменений.
PROMPT;

        $userPrompt = "Запрос клиента:\n{$message}\n\nПодготовленный кейс:\n{$reportExcerpt}";

        $content = $this->f5AiService->chatCompletion([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ], [
            'model' => config('f5ai.model'),
            'max_tokens' => (int) config('f5ai.max_tokens_changes', 12000),
            'temperature' => 0.3,
        ]);

        $parsed = $this->changesParser->parse($content);

        return $parsed['changes'];
    }
}

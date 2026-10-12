<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectSkkField;
use App\Support\F5AiModels;
use App\Support\F5WidgetCatalog;

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

        if (mb_strlen($message) < 300) {
            return false;
        }

        return (bool) preg_match(
            '/(кейс\s+внедрен|полн(ый|ое)\s+(кейс|план)|опиши\s+внедрен|подготовь\s+(кейс|план)|техническое\s+задание|детальн(ый|ое)\s+(кейс|план))/ui',
            $message
        );
    }

    /**
     * @return array{report: string, changes: array<string, mixed>|null}
     */
    public function process(Project $project, string $message, ?int $preferredUserId = null): array
    {
        $report = $this->generateReport($project, $message, $preferredUserId);
        $changes = $this->extractChanges($project, $message, $report, $preferredUserId);

        return [
            'report' => $report,
            'changes' => $changes,
        ];
    }

    private function generateReport(Project $project, string $message, ?int $preferredUserId = null): string
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
            'user' => F5AiModels::resolveUser($project, $preferredUserId),
            'large' => true,
            'max_tokens' => (int) config('f5ai.max_tokens_report', 16000),
            'temperature' => 0.5,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractChanges(
        Project $project,
        string $message,
        string $report,
        ?int $preferredUserId = null
    ): ?array {
        $context = $this->contextBuilder->build($project);
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);
        $widgetRules = F5WidgetCatalog::integrationPromptRules();

        $systemPrompt = <<<PROMPT
Ты — CRM-архитектор. По запросу клиента и подготовленному кейсу сформируй изменения для проекта в CRM-планировщике.

Текущее состояние проекта (id для update/delete; f5_widgets — каталог виджетов команды F5):
{$contextJson}

Ответь ТОЛЬКО валидным JSON без markdown:
{
  "message": "Краткое резюме: что будет загружено в проект",
  "changes": {
    "project": { "fields": { "company_name": "...", "business_sphere": "...", "employees_count": null, "client_tasks": "краткое резюме задач" } },
    "skk_fields": {
      "create": [{"entity_type": "deals|contacts|companies", "name": "...", "value_type": "text|number|date|select|multiselect|toggle", "is_required": true, "show_in_card": true, "show_in_list": false, "show_in_filter": false, "options": ["Вариант 1"], "funnel_stage_id": null, "comment": "зачем нужно поле"}],
      "update": [],
      "delete": []
    },
    "integrations": {
      "create": [{"widget_slug": "chatter", "comment": "какую проблему решает и сценарий"}],
      "update": [],
      "delete": []
    },
    "funnels": {
      "create": [{
        "name": "Название воронки",
        "description": "Цель воронки",
        "stages": [
          {"name": "Этап", "action": "Автозадача менеджеру", "comment": "Триггер перехода", "stage_type": "Тип этапа", "deadline": "SLA", "kind": "normal", "widgets": [], "outcomes": [{"result": "Успех", "mode": "move", "target_sort_order": 2}]}
        ]
      }],
      "update": [],
      "delete": []
    },
    "stages": { "create": [], "update": [], "delete": [] }
  }
}

{$widgetRules}

Правила:
- Заполни проект по кейсу: минимум 8–12 полей (deals+contacts+companies), 2–4 воронки с детальными этапами, 5–10 интеграций/виджетов в разделе integrations.
- Автозадачи и outcomes на этапах — базово без виджетов: widgets: [], в outcomes без widget_slug. Виджеты на этапах добавляй только если в запросе/кейсе явно требуется запуск виджета на этапе или результате.
- Новые воронки создавай через funnels.create со вложенным массивом stages (не используй funnel_id для новых воронок).
- Для существующих элементов используй update/delete по id. Для полной перестройки — delete старых + create новых.
- comment у полей — дополнительное пояснение; value_type, обязательность и отображение указывай явно; options — для select/multiselect.
- Не выдумывай id. Если проект пустой — только create.
- Не отказывайся. Верни максимально полный набор изменений.
PROMPT;

        $userPrompt = "Запрос клиента:\n{$message}\n\nПодготовленный кейс:\n{$report}";

        $content = $this->f5AiService->chatCompletion([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ], [
            'user' => F5AiModels::resolveUser($project, $preferredUserId),
            'max_tokens' => (int) config('f5ai.max_tokens_changes', 12000),
            'temperature' => 0.3,
        ]);

        $parsed = $this->changesParser->parse($content);

        return $parsed['changes'];
    }
}

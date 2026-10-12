<?php

namespace App\Services;

use App\Models\Project;
use App\Support\F5WidgetCatalog;

class ProjectAiPlanner
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<int, array{role: string, content: string}>
     */
    public function buildMessages(Project $project, array $input): array
    {
        $sections = $input['sections'] ?? [];
        $companyName = $input['company_name'] ?? $project->company_name;
        $employeesCount = $input['employees_count'] ?? $project->employees_count;
        $clientTasks = $input['client_tasks'] ?? $project->client_tasks;
        $extraInstructions = trim((string) ($input['extra_instructions'] ?? ''));

        $sectionDescriptions = [];

        if (in_array('skk_fields', $sections, true)) {
            $sectionDescriptions[] = '- skk_fields: пользовательские поля CRM для сделок (deals), контактов (contacts) и компаний (companies). Каждое поле: name, value_type (text|number|date|select|multiselect|toggle), is_required (bool), show_in_card/show_in_list/show_in_filter (bool), options (массив строк для select/multiselect), funnel_stage_id (id этапа, если поле заполняется на этапе), comment (доп. пояснение).';
        }

        if (in_array('integrations', $sections, true)) {
            $sectionDescriptions[] = '- integrations: виджеты команды F5 из каталога f5_widgets. Каждый элемент: widget_slug (точный slug из каталога), comment (сценарий). name и url подставятся автоматически. Если виджета нет в каталоге — widget_slug: null, name, comment.';
        }

        if (in_array('funnels', $sections, true)) {
            $sectionDescriptions[] = '- funnels: воронки продаж с этапами. Каждая воронка: name, description (кратко), stages — массив этапов с полями name, action (что делает менеджер на этапе), comment.';
        }

        $sectionsText = implode("\n", $sectionDescriptions);
        $widgetCatalogJson = in_array('integrations', $sections, true)
            ? json_encode(F5WidgetCatalog::forAiContext(), JSON_UNESCAPED_UNICODE)
            : '';
        $widgetRules = in_array('integrations', $sections, true)
            ? F5WidgetCatalog::integrationPromptRules()
            : '';

        $systemPrompt = <<<PROMPT
Ты — эксперт по внедрению CRM для российского бизнеса. По данным о компании клиента предложи структуру проекта.

Ответь ТОЛЬКО валидным JSON без markdown и пояснений до или после JSON.

Схема ответа:
{
  "skk_fields": {
    "deals": [{"name": "...", "value_type": "text", "is_required": true, "show_in_card": true, "show_in_list": false, "show_in_filter": false, "options": [], "comment": "..."}],
    "contacts": [{"name": "...", "comment": "..."}],
    "companies": [{"name": "...", "comment": "..."}]
  },
  "integrations": [{"widget_slug": "chatter", "comment": "..."}],
  "funnels": [
    {
      "name": "...",
      "description": "...",
      "stages": [{"name": "...", "action": "...", "comment": "..."}]
    }
  ]
}

Включай в JSON только запрошенные разделы:
{$sectionsText}

{$widgetRules}
Каталог виджетов F5 (f5_widgets):
{$widgetCatalogJson}

Для незапрошенных разделов не добавляй ключи. Названия на русском, конкретные и применимые к бизнесу клиента.
PROMPT;

        $userPrompt = "Компания: {$companyName}\n";

        if ($employeesCount !== null && $employeesCount !== '') {
            $userPrompt .= "Количество сотрудников: {$employeesCount}\n";
        }

        if (! empty($clientTasks)) {
            $userPrompt .= "Задачи клиента:\n{$clientTasks}\n";
        }

        if ($extraInstructions !== '') {
            $userPrompt .= "\nДополнительные требования:\n{$extraInstructions}\n";
        }

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => trim($userPrompt)],
        ];
    }
}

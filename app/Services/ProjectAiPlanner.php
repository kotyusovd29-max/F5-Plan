<?php

namespace App\Services;

use App\Models\Project;

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
            $sectionDescriptions[] = '- skk_fields: пользовательские поля CRM для сделок (deals), контактов (contacts) и компаний (companies). Каждое поле: name (краткое название), comment (зачем нужно поле).';
        }

        if (in_array('integrations', $sections, true)) {
            $sectionDescriptions[] = '- integrations: интеграции и виджеты (телефония, мессенджеры, сайт и т.д.). Каждый элемент: name, comment.';
        }

        if (in_array('funnels', $sections, true)) {
            $sectionDescriptions[] = '- funnels: воронки продаж с этапами. Каждая воронка: name, description (кратко), stages — массив этапов с полями name, action (что делает менеджер на этапе), comment.';
        }

        $sectionsText = implode("\n", $sectionDescriptions);

        $systemPrompt = <<<PROMPT
Ты — эксперт по внедрению CRM для российского бизнеса. По данным о компании клиента предложи структуру проекта.

Ответь ТОЛЬКО валидным JSON без markdown и пояснений до или после JSON.

Схема ответа:
{
  "skk_fields": {
    "deals": [{"name": "...", "comment": "..."}],
    "contacts": [{"name": "...", "comment": "..."}],
    "companies": [{"name": "...", "comment": "..."}]
  },
  "integrations": [{"name": "...", "comment": "..."}],
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

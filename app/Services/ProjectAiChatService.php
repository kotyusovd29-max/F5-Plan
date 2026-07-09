<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAiMessage;
use Illuminate\Support\Collection;

class ProjectAiChatService
{
    private const HISTORY_LIMIT = 12;

    public function __construct(
        private readonly F5AiService $f5AiService,
        private readonly ProjectAiContextBuilder $contextBuilder,
        private readonly ProjectAiChangesParser $changesParser,
        private readonly ProjectAiImplementationService $implementationService
    ) {}

    /**
     * @return array{user: ProjectAiMessage, assistant: ProjectAiMessage}
     */
    public function sendMessage(Project $project, string $message): array
    {
        if ($this->implementationService->isImplementationBrief($message)) {
            return $this->sendImplementationBrief($project, $message);
        }

        return $this->sendRegularMessage($project, $message);
    }

    /**
     * @return array{user: ProjectAiMessage, assistant: ProjectAiMessage}
     */
    private function sendImplementationBrief(Project $project, string $message): array
    {
        $userMessage = $project->aiMessages()->create([
            'role' => ProjectAiMessage::ROLE_USER,
            'content' => $message,
        ]);

        $result = $this->implementationService->process($project, $message);

        $footer = "\n\n---\n**Настройки проекта подготовлены.** Нажмите «Применить изменения» под этим сообщением, чтобы загрузить воронки, поля и интеграции в проект.";

        $assistantMessage = $project->aiMessages()->create([
            'role' => ProjectAiMessage::ROLE_ASSISTANT,
            'content' => $result['report'].$footer,
            'changes' => $result['changes'],
        ]);

        return [
            'user' => $userMessage,
            'assistant' => $assistantMessage,
        ];
    }

    /**
     * @return array{user: ProjectAiMessage, assistant: ProjectAiMessage}
     */
    private function sendRegularMessage(Project $project, string $message): array
    {
        $history = $this->historyForApi($project);
        $context = $this->contextBuilder->build($project);
        $apiMessages = $this->buildApiMessages($context, $history, $message);

        $userMessage = $project->aiMessages()->create([
            'role' => ProjectAiMessage::ROLE_USER,
            'content' => $message,
        ]);

        $content = $this->f5AiService->chatCompletion($apiMessages, [
            'max_tokens' => (int) config('f5ai.max_tokens', 8000),
        ]);
        $parsed = $this->changesParser->parse($content);

        $assistantMessage = $project->aiMessages()->create([
            'role' => ProjectAiMessage::ROLE_ASSISTANT,
            'content' => $parsed['message'],
            'changes' => $parsed['changes'],
        ]);

        return [
            'user' => $userMessage,
            'assistant' => $assistantMessage,
        ];
    }

    /**
     * @return Collection<int, ProjectAiMessage>
     */
    private function historyForApi(Project $project): Collection
    {
        return $project->aiMessages()
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  Collection<int, ProjectAiMessage>  $history
     * @return array<int, array{role: string, content: string}>
     */
    private function buildApiMessages(array $context, Collection $history, string $message): array
    {
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);

        $systemPrompt = <<<PROMPT
Ты — эксперт по внедрению CRM. Помогаешь настраивать проект: поля сделок/контактов/компаний, интеграции, воронки и этапы.

Текущее состояние проекта (JSON с id для редактирования):
{$contextJson}

Отвечай ТОЛЬКО валидным JSON без markdown:
{
  "message": "Ответ пользователю на русском (может быть развёрнутым)",
  "changes": {
    "project": { "fields": { "company_name": "...", "employees_count": 10, "client_tasks": "..." } },
    "skk_fields": {
      "create": [{"entity_type": "contacts", "name": "...", "comment": "..."}],
      "update": [{"id": 1, "name": "...", "comment": "..."}],
      "delete": [2]
    },
    "integrations": { "create": [], "update": [], "delete": [] },
    "funnels": {
      "create": [{"name": "...", "description": "...", "stages": [{"name": "...", "action": "...", "comment": "..."}]}],
      "update": [{"id": 1, "name": "...", "description": "..."}],
      "delete": []
    },
    "stages": {
      "create": [{"funnel_id": 1, "name": "...", "action": "...", "comment": "..."}],
      "update": [{"id": 5, "name": "...", "action": "...", "comment": "..."}],
      "delete": []
    }
  }
}

Правила:
- Не отказывайся от запросов. Делай максимум в рамках возможностей.
- Если нужны только правки — заполни changes. Если только консультация — changes = null.
- Для новых воронок используй funnels.create со вложенным stages.
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
                $messages[] = ['role' => 'assistant', 'content' => mb_substr($item->content, 0, 4000)];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }
}

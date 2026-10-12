<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectAiMessage;
use App\Services\ProjectAiChatService;
use Illuminate\Console\Command;

class ProcessProjectAiChatMessage extends Command
{
    protected $signature = 'ai:process-chat-message
        {project : ID проекта}
        {userMessage : ID сообщения пользователя}
        {assistantMessage : ID сообщения ассистента}
        {mode? : Режим обработки}';

    protected $description = 'Обработать отложенный ответ ИИ в отдельном процессе';

    public function handle(ProjectAiChatService $chatService): int
    {
        $projectId = (int) $this->argument('project');
        $userMessageId = (int) $this->argument('userMessage');
        $assistantMessageId = (int) $this->argument('assistantMessage');
        $mode = $this->argument('mode') ?: null;

        $project = Project::query()->find($projectId);
        $userMessage = ProjectAiMessage::query()->find($userMessageId);
        $assistantMessage = ProjectAiMessage::query()->find($assistantMessageId);

        if ($project === null || $userMessage === null || $assistantMessage === null) {
            $this->error("AI process missing records: project={$projectId}, user={$userMessageId}, assistant={$assistantMessageId}");

            return self::FAILURE;
        }

        $this->info("Processing AI message #{$assistantMessageId} for project #{$projectId}");

        $chatService->processPendingResponse(
            $project,
            $userMessage,
            $assistantMessage,
            $mode === '' ? null : $mode
        );

        $assistantMessage->refresh();
        $this->info("Finished AI message #{$assistantMessageId} with status={$assistantMessage->status}");

        return self::SUCCESS;
    }
}

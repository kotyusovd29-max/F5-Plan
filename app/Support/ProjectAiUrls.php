<?php

namespace App\Support;

use App\Models\Project;

class ProjectAiUrls
{
    /**
     * @return array<string, string>
     */
    public static function forProject(Project $project): array
    {
        return [
            'ai_chat' => route('projects.ai.chat', $project),
            'ai_messages' => route('projects.ai.chat.messages', $project),
            'ai_send' => route('projects.ai.chat.send', $project),
            'ai_cancel_template' => route('projects.ai.chat.cancel', [$project, '__MESSAGE__']),
            'ai_analyze' => route('projects.ai.analyze', $project),
            'ai_apply_template' => route('projects.ai.chat.apply', [$project, '__MESSAGE__']),
            'ai_rollback_template' => route('projects.ai.chat.rollback', [$project, '__MESSAGE__']),
            'brief_documents_store' => route('projects.brief-documents.store', $project),
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAiMessage;

class ProjectAiReportExtractor
{
    public function latestReport(Project $project): ?string
    {
        $project->loadMissing('aiMessages');

        $message = $project->aiMessages
            ->where('thread', ProjectAiMessage::THREAD_PROJECT)
            ->where('role', ProjectAiMessage::ROLE_ASSISTANT)
            ->sortByDesc('id')
            ->first(fn (ProjectAiMessage $item) => $this->isAnalyticalReport($item));

        if (! $message) {
            return null;
        }

        return $this->cleanContent($message->content);
    }

    private function isAnalyticalReport(ProjectAiMessage $message): bool
    {
        if ($message->changesHasOperations()) {
            return true;
        }

        $content = trim($message->content);

        return mb_strlen($content) >= 600
            && preg_match('/(внедрен|воронк|amo\s*crm|кейс|аудит|эталон|b2b)/ui', $content);
    }

    public function cleanContent(string $content): string
    {
        $content = trim($content);
        $parts = preg_split('/\R---\R/', $content, 2);

        return trim($parts[0] ?? $content);
    }
}

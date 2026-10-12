<?php

namespace App\Services;

use App\Models\Funnel;
use App\Models\FunnelBoardComment;
use App\Models\Project;
use App\Models\ProjectAiMessage;
use App\Models\User;
use App\Models\UserNotification;

class UserNotificationService
{
    public function notifyAiReady(Project $project, ProjectAiMessage $assistantMessage): void
    {
        $recipientId = $assistantMessage->user_id ?: $project->user_id;

        if (! $recipientId) {
            return;
        }

        $failed = $assistantMessage->status === ProjectAiMessage::STATUS_FAILED;
        $threadLabel = $assistantMessage->threadLabel();

        UserNotification::query()->create([
            'user_id' => $recipientId,
            'type' => $failed ? UserNotification::TYPE_AI_FAILED : UserNotification::TYPE_AI_READY,
            'title' => $failed ? 'ИИ не смог ответить' : 'Ответ ИИ готов',
            'body' => $failed
                ? "Проект «{$project->company_name}» · {$threadLabel}: произошла ошибка."
                : "Проект «{$project->company_name}» · {$threadLabel}: можно открыть чат.",
            'url' => route('projects.ai.chat', [
                'project' => $project,
                'thread' => $assistantMessage->thread,
            ]),
            'data' => [
                'project_id' => $project->id,
                'message_id' => $assistantMessage->id,
                'thread' => $assistantMessage->thread,
            ],
        ]);
    }

    public function notifyEditorAccess(Project $project, User $editor): void
    {
        UserNotification::query()->create([
            'user_id' => $editor->id,
            'type' => UserNotification::TYPE_EDITOR_ACCESS,
            'title' => 'Выдан доступ к проекту',
            'body' => "Вам открыто редактирование плана «{$project->company_name}».",
            'url' => route('projects.show', $project),
            'data' => [
                'project_id' => $project->id,
            ],
        ]);
    }

    public function notifyBoardComment(Funnel $funnel, FunnelBoardComment $comment): void
    {
        $project = $funnel->project;
        if (! $project) {
            return;
        }

        $project->loadMissing('user');

        // Только команда проекта (менеджер, его РОП, назначенные сотрудники) — не клиент.
        $recipientIds = collect([
            $project->user_id,
            $project->user?->rop_id,
        ])
            ->merge($project->editors()->pluck('users.id')->all())
            ->filter()
            ->unique()
            ->values();

        $author = trim((string) $comment->author_name) ?: 'Клиент';
        $preview = mb_substr(trim((string) $comment->body), 0, 120);
        $url = route('projects.funnels.canvas.show', [$project, $funnel]);

        foreach ($recipientIds as $recipientId) {
            UserNotification::query()->create([
                'user_id' => $recipientId,
                'type' => UserNotification::TYPE_BOARD_COMMENT,
                'title' => 'Комментарий на доске воронки',
                'body' => "«{$funnel->name}» · {$author}: {$preview}",
                'url' => $url,
                'data' => [
                    'project_id' => $project->id,
                    'funnel_id' => $funnel->id,
                    'comment_id' => $comment->id,
                ],
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function unreadForFrontend(User $user, int $limit = 20): array
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->unread()
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (UserNotification $notification) => $notification->toFrontend())
            ->values()
            ->all();
    }
}

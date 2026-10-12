<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicFunnelBoardCommentRequest;
use App\Models\Funnel;
use App\Models\FunnelBoardComment;
use App\Models\Project;
use App\Services\FunnelCanvasLayoutService;
use App\Services\UserNotificationService;
use App\Support\BoardShare;
use App\Support\F5WidgetCatalog;
use App\Support\FunnelBoardWidgets;
use App\Support\FunnelStageWidgets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class PublicFunnelBoardController extends Controller
{
    public function show(string $token, FunnelCanvasLayoutService $layoutService): Response
    {
        $resolved = BoardShare::resolveOrFail($token);
        /** @var Project $project */
        $project = $resolved['project'];
        /** @var Funnel|null $legacyFunnel */
        $legacyFunnel = $resolved['legacy_funnel'];

        if ($this->isExpired($project, $legacyFunnel)) {
            return Inertia::render('Funnels/PublicBoardUnavailable', [
                'reason' => 'expired',
                'company_name' => $project->company_name,
                'funnel_name' => null,
            ]);
        }

        if ($this->requiresPassword($project, $legacyFunnel) && ! $this->isShareUnlocked($project)) {
            return Inertia::render('Funnels/PublicBoardPassword', [
                'token' => $token,
                'company_name' => $project->company_name,
                'funnel_name' => $project->company_name,
                'unlock_url' => route('funnels.board.public.unlock', ['token' => $token]),
            ]);
        }

        $funnel = BoardShare::activeFunnel(
            $project,
            request()->integer('funnel') ?: null,
            $legacyFunnel
        );

        $layoutService->ensureStagePositions($funnel);
        $funnel->load(['stages', 'boardComments']);

        $commentsEnabled = $this->commentsEnabled($project, $legacyFunnel);
        $project->loadMissing('funnels');

        return Inertia::render('Funnels/PublicBoard', [
            'project' => [
                'company_name' => $project->company_name,
            ],
            'funnels' => $project->funnels->map(fn (Funnel $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'url' => route('funnels.board.public', [
                    'token' => $token,
                    'funnel' => $item->id,
                ]),
            ])->values()->all(),
            'funnel' => [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'description' => $funnel->description,
                'stages' => $funnel->stages->values()->map(function ($stage, int $index) {
                    return [
                        'id' => $stage->id,
                        'number' => $index + 1,
                        'name' => $stage->name,
                        'action' => $stage->action,
                        'comment' => $stage->comment,
                        'stage_type' => $stage->stage_type,
                        'deadline' => $stage->deadline,
                        'kind' => $stage->kind ?: 'normal',
                        'outcomes' => is_array($stage->outcomes) ? $stage->outcomes : [],
                        'widgets' => FunnelStageWidgets::present(
                            is_array($stage->widgets) ? $stage->widgets : []
                        ),
                        'sort_order' => $stage->sort_order,
                        'canvas_pos_x' => $stage->canvas_pos_x,
                        'canvas_pos_y' => $stage->canvas_pos_y,
                    ];
                })->all(),
                'board_widgets' => FunnelBoardWidgets::present(
                    is_array($funnel->board_widgets) ? $funnel->board_widgets : []
                ),
            ],
            'comments' => $commentsEnabled
                ? $funnel->boardComments
                    ->map(fn (FunnelBoardComment $comment) => $comment->toFrontend())
                    ->values()
                    ->all()
                : [],
            'commentsEnabled' => $commentsEnabled,
            'urls' => [
                'comments_store' => $commentsEnabled
                    ? route('funnels.board.public.comments.store', [
                        'token' => $token,
                        'funnel' => $funnel->id,
                    ])
                    : null,
                'comments_destroy_base' => $commentsEnabled
                    ? url('/b/'.$token.'/comments')
                    : null,
            ],
            'viewport' => [
                'offset_x' => (int) $funnel->canvas_offset_x,
                'offset_y' => (int) $funnel->canvas_offset_y,
                'zoom' => (float) ($funnel->canvas_zoom ?: 1),
            ],
            'f5WidgetCatalog' => F5WidgetCatalog::forFrontend(),
        ]);
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $resolved = BoardShare::resolveOrFail($token);
        /** @var Project $project */
        $project = $resolved['project'];
        /** @var Funnel|null $legacyFunnel */
        $legacyFunnel = $resolved['legacy_funnel'];

        if ($this->isExpired($project, $legacyFunnel)) {
            return back()->withErrors([
                'password' => 'Срок действия ссылки истёк.',
            ]);
        }

        $password = (string) $request->input('password', '');

        if (! $this->checkPassword($project, $legacyFunnel, $password)) {
            return back()->withErrors([
                'password' => 'Неверный пароль.',
            ]);
        }

        $request->session()->put($this->shareUnlockKey($project), true);

        return redirect()->route('funnels.board.public', [
            'token' => $token,
            'funnel' => $request->integer('funnel') ?: null,
        ]);
    }

    public function storeComment(
        StorePublicFunnelBoardCommentRequest $request,
        string $token,
        UserNotificationService $notifications
    ): RedirectResponse {
        $resolved = BoardShare::resolveOrFail($token);
        /** @var Project $project */
        $project = $resolved['project'];
        /** @var Funnel|null $legacyFunnel */
        $legacyFunnel = $resolved['legacy_funnel'];

        $funnel = $request->funnel() ?? BoardShare::activeFunnel(
            $project,
            $request->integer('funnel') ?: null,
            $legacyFunnel
        );

        if ($this->isExpired($project, $legacyFunnel)) {
            return back()->withErrors([
                'body' => 'Срок действия ссылки истёк.',
            ]);
        }

        if ($this->requiresPassword($project, $legacyFunnel) && ! $this->isShareUnlocked($project)) {
            return back()->withErrors([
                'body' => 'Сначала введите пароль доступа к доске.',
            ]);
        }

        if (! $this->commentsEnabled($project, $legacyFunnel)) {
            return back()->withErrors([
                'body' => 'Комментарии на этой доске отключены.',
            ]);
        }

        $rateKey = 'funnel-board-comment:'.sha1($token.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($rateKey, 8)) {
            return back()->withErrors([
                'body' => 'Слишком много комментариев. Подождите немного и попробуйте снова.',
            ]);
        }

        RateLimiter::hit($rateKey, 600);

        $data = $request->validated();
        $stageId = isset($data['funnel_stage_id']) ? (int) $data['funnel_stage_id'] : null;

        $comment = FunnelBoardComment::query()->create([
            'funnel_id' => $funnel->id,
            'funnel_stage_id' => $stageId,
            'author_name' => trim((string) $data['author_name']),
            'body' => trim((string) $data['body']),
            'ip_hash' => hash('sha256', (string) $request->ip()),
        ]);

        $notifications->notifyBoardComment($funnel->setRelation('project', $project), $comment);

        return back()->with('success', 'Комментарий отправлен');
    }

    public function destroyComment(string $token, FunnelBoardComment $comment): RedirectResponse
    {
        $resolved = BoardShare::resolveOrFail($token);
        /** @var Project $project */
        $project = $resolved['project'];
        /** @var Funnel|null $legacyFunnel */
        $legacyFunnel = $resolved['legacy_funnel'];

        abort_unless(
            $project->funnels()->whereKey($comment->funnel_id)->exists(),
            404
        );

        if ($this->isExpired($project, $legacyFunnel) || ! $this->commentsEnabled($project, $legacyFunnel)) {
            return back()->withErrors([
                'body' => 'Удаление комментариев недоступно.',
            ]);
        }

        $rateKey = 'funnel-board-comment-delete:'.sha1($token.'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($rateKey, 20)) {
            return back()->withErrors([
                'body' => 'Слишком много удалений. Подождите немного и попробуйте снова.',
            ]);
        }

        RateLimiter::hit($rateKey, 600);

        $comment->delete();

        return back()->with('success', 'Комментарий удалён');
    }

    private function isExpired(Project $project, ?Funnel $legacyFunnel): bool
    {
        if (filled($project->board_share_token)) {
            return $project->isBoardShareExpired();
        }

        return $legacyFunnel?->isShareExpired() ?? false;
    }

    private function requiresPassword(Project $project, ?Funnel $legacyFunnel): bool
    {
        if (filled($project->board_share_token)) {
            return $project->hasBoardSharePassword();
        }

        return $legacyFunnel?->hasSharePassword() ?? false;
    }

    private function checkPassword(Project $project, ?Funnel $legacyFunnel, string $password): bool
    {
        if (filled($project->board_share_token)) {
            return $project->checkBoardSharePassword($password);
        }

        return $legacyFunnel?->checkSharePassword($password) ?? false;
    }

    private function commentsEnabled(Project $project, ?Funnel $legacyFunnel): bool
    {
        if (filled($project->board_share_token)) {
            return $project->boardCommentsEnabled();
        }

        return $legacyFunnel?->commentsEnabled() ?? true;
    }

    private function isShareUnlocked(Project $project): bool
    {
        return (bool) session($this->shareUnlockKey($project));
    }

    private function shareUnlockKey(Project $project): string
    {
        return 'project_board_share_unlocked_'.$project->id;
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\AiChatPresenter;
use App\Services\TrainingProjectService;
use App\Services\UserNotificationService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'is_admin' => $request->user()->isAdmin(),
                    'role' => $request->user()->isAdmin() ? 'admin' : $request->user()->role,
                    'role_label' => $request->user()->roleLabel(),
                    'rop_id' => $request->user()->rop_id,
                    'ai_model' => $request->user()->ai_model,
                    'google_connected' => $request->user()->hasGoogleConnected(),
                    'google_email' => $request->user()->google_email,
                ] : null,
            ],
            'google' => [
                'configured' => filled(config('services.google.client_id'))
                    && filled(config('services.google.client_secret')),
                'connect_url' => route('google.connect'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'amocrm_import_open' => fn () => (bool) $request->session()->get('amocrm_import_open'),
            ],
            'app' => [
                'name' => config('app.name'),
            ],
            'aiChat' => fn () => $request->user()
                ? AiChatPresenter::sharedPayload($request->user())
                : null,
            'aiModels' => fn () => $request->user()
                ? AiChatPresenter::modelsPayload($request->user())
                : null,
            'training' => function () use ($request) {
                if (! $request->user()) {
                    return null;
                }

                try {
                    return app(TrainingProjectService::class)->sharedPayload();
                } catch (\Throwable) {
                    return null;
                }
            },
            'notifications' => function () use ($request) {
                if (! $request->user()) {
                    return [];
                }

                // Клиентская доска /b/* не должна получать служебные уведомления менеджера.
                if ($this->isPublicBoardRequest($request)) {
                    return [];
                }

                try {
                    return app(UserNotificationService::class)->unreadForFrontend($request->user());
                } catch (\Throwable) {
                    return [];
                }
            },
        ];
    }

    private function isPublicBoardRequest(Request $request): bool
    {
        return $request->routeIs([
            'funnels.board.public',
            'funnels.board.public.unlock',
            'funnels.board.public.comments.store',
            'funnels.board.public.comments.destroy',
        ]) || $request->is('b', 'b/*');
    }
}

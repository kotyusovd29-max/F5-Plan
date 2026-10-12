<?php

namespace App\Http\Controllers;

use App\Exceptions\GoogleDocsException;
use App\Services\GoogleOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoogleAccountController extends Controller
{
    public function __construct(
        private readonly GoogleOAuthService $oauth
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->oauth->isConfigured()) {
            return redirect()
                ->route('profile.edit')
                ->with('error', 'Google OAuth не настроен на сервере. Добавьте GOOGLE_CLIENT_ID и GOOGLE_CLIENT_SECRET.');
        }

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away($this->oauth->getAuthorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('google_oauth_state');
        $state = $request->string('state')->toString();

        if (! is_string($expectedState) || $expectedState === '' || ! hash_equals($expectedState, $state)) {
            return redirect()
                ->route('profile.edit')
                ->with('error', 'Неверный state OAuth. Попробуйте подключить Google снова.');
        }

        if ($request->filled('error')) {
            return redirect()
                ->route('profile.edit')
                ->with('error', 'Доступ к Google отклонён: '.$request->string('error')->toString());
        }

        $code = $request->string('code')->toString();

        if ($code === '') {
            return redirect()
                ->route('profile.edit')
                ->with('error', 'Google не вернул код авторизации.');
        }

        try {
            $tokenData = $this->oauth->exchangeAuthorizationCode($code);
            $this->oauth->connectUser($request->user(), $tokenData);
        } catch (GoogleDocsException $exception) {
            return redirect()
                ->route('profile.edit')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('profile.edit')
            ->with('success', 'Google-аккаунт подключён. Теперь можно сохранять документы в Google Docs.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $this->oauth->disconnectUser($request->user());

        return redirect()
            ->route('profile.edit')
            ->with('success', 'Google-аккаунт отключён.');
    }
}

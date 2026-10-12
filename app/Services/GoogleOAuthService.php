<?php

namespace App\Services;

use App\Exceptions\GoogleDocsException;
use App\Models\User;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Log;

class GoogleOAuthService
{
    public const SCOPE_DRIVE_FILE = 'https://www.googleapis.com/auth/drive.file';

    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    public function createClient(): GoogleClient
    {
        if (! $this->isConfigured()) {
            throw new GoogleDocsException(
                'Google OAuth не настроен. Укажите GOOGLE_CLIENT_ID и GOOGLE_CLIENT_SECRET в .env.'
            );
        }

        $client = new GoogleClient;
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        // Incremental auth часто оставляет старый токен без Drive — запрашиваем полный набор заново.
        $client->setIncludeGrantedScopes(false);
        $client->setScopes([
            self::SCOPE_DRIVE_FILE,
            'openid',
            'email',
            'profile',
        ]);

        return $client;
    }

    public function getAuthorizationUrl(string $state): string
    {
        $client = $this->createClient();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    /**
     * @return array{google_id: string, google_email: string, access_token: string, refresh_token: ?string, expires_at: ?\Illuminate\Support\Carbon}
     */
    public function exchangeAuthorizationCode(string $code): array
    {
        $client = $this->createClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new GoogleDocsException(
                'Не удалось получить токен Google: '.($token['error_description'] ?? $token['error'])
            );
        }

        $client->setAccessToken($token);

        $grantedScopes = (string) ($token['scope'] ?? '');

        if (
            $grantedScopes !== ''
            && ! str_contains($grantedScopes, 'drive.file')
            && ! str_contains($grantedScopes, 'googleapis.com/auth/drive')
        ) {
            throw new GoogleDocsException(
                'Google не выдал доступ к Drive. В OAuth consent screen добавьте scope «.../auth/drive.file», включите Google Drive API и подключите аккаунт заново.'
            );
        }

        $oauth2 = new \Google\Service\Oauth2($client);
        $profile = $oauth2->userinfo->get();

        $expiresAt = null;

        if (! empty($token['expires_in'])) {
            $expiresAt = now()->addSeconds((int) $token['expires_in']);
        }

        return [
            'google_id' => (string) $profile->getId(),
            'google_email' => (string) ($profile->getEmail() ?? ''),
            'access_token' => json_encode($token, JSON_UNESCAPED_UNICODE),
            'refresh_token' => $token['refresh_token'] ?? null,
            'expires_at' => $expiresAt,
        ];
    }

    public function connectUser(User $user, array $tokenData): void
    {
        $payload = [
            'google_id' => $tokenData['google_id'],
            'google_email' => $tokenData['google_email'] !== '' ? $tokenData['google_email'] : null,
            'google_access_token' => $tokenData['access_token'],
            'google_token_expires_at' => $tokenData['expires_at'],
        ];

        if (! empty($tokenData['refresh_token'])) {
            $payload['google_refresh_token'] = $tokenData['refresh_token'];
        } elseif (! $user->google_refresh_token) {
            throw new GoogleDocsException(
                'Google не вернул refresh token. Отключите доступ приложению в аккаунте Google и подключите снова.'
            );
        }

        $user->forceFill($payload)->save();
    }

    public function disconnectUser(User $user): void
    {
        try {
            if ($user->hasGoogleConnected()) {
                $client = $this->clientForUser($user);
                $token = $client->getAccessToken();
                $accessToken = is_array($token) ? ($token['access_token'] ?? null) : null;

                if (is_string($accessToken) && $accessToken !== '') {
                    $client->revokeToken($accessToken);
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('Google token revoke failed', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);
        }

        $user->forceFill([
            'google_id' => null,
            'google_email' => null,
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ])->save();
    }

    public function clientForUser(User $user): GoogleClient
    {
        if (! $user->hasGoogleConnected()) {
            throw new GoogleDocsException('Google-аккаунт не подключён.');
        }

        $client = $this->createClient();
        $token = json_decode((string) $user->google_access_token, true);

        if (! is_array($token)) {
            $token = [
                'access_token' => (string) $user->google_access_token,
                'refresh_token' => $user->google_refresh_token,
            ];
        }

        if ($user->google_refresh_token && empty($token['refresh_token'])) {
            $token['refresh_token'] = $user->google_refresh_token;
        }

        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            if (empty($token['refresh_token'])) {
                throw new GoogleDocsException(
                    'Сессия Google истекла. Подключите Google-аккаунт заново в профиле.'
                );
            }

            $fresh = $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);

            if (isset($fresh['error'])) {
                throw new GoogleDocsException(
                    'Не удалось обновить токен Google: '.($fresh['error_description'] ?? $fresh['error'])
                );
            }

            $merged = array_merge($token, $fresh);
            $client->setAccessToken($merged);

            $user->forceFill([
                'google_access_token' => json_encode($merged, JSON_UNESCAPED_UNICODE),
                'google_token_expires_at' => ! empty($fresh['expires_in'])
                    ? now()->addSeconds((int) $fresh['expires_in'])
                    : null,
                'google_refresh_token' => $merged['refresh_token'] ?? $user->google_refresh_token,
            ])->save();
        }

        return $client;
    }
}

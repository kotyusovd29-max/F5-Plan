<?php

namespace App\Services;

use App\Exceptions\F5AiException;
use App\Models\User;
use App\Support\F5AiModels;
use App\Support\LongRunningRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class F5AiService
{
    /**
     * @param  array<int, array{role: string, content: string|list<array<string, mixed>>}>  $messages
     * @param  array{
     *     model?: string|null,
     *     large?: bool,
     *     user?: User|null,
     *     temperature?: float|int,
     *     max_tokens?: int,
     *     timeout?: int,
     *     on_wait?: callable(): void
     * }  $options
     * @param  callable(string $delta): void  $onDelta  Колбэк на каждый текстовый фрагмент ответа.
     */
    public function chatCompletionStreamed(array $messages, array $options, callable $onDelta): string
    {
        $apiKey = config('f5ai.api_key');

        if (empty($apiKey)) {
            throw new F5AiException('F5AI API ключ не настроен. Добавьте F5AI_API_KEY в файл .env.');
        }

        LongRunningRequest::disableTimeouts();

        $timeout = (int) ($options['timeout'] ?? config('f5ai.timeout', 300));
        $user = $options['user'] ?? null;
        $large = (bool) ($options['large'] ?? false);

        $model = $options['model'] ?? null;
        if (! is_string($model) || $model === '') {
            $model = F5AiModels::resolve($user instanceof User ? $user : null, $large);
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.4,
            'stream' => true,
        ];

        $maxTokens = array_key_exists('max_tokens', $options)
            ? (int) $options['max_tokens']
            : (int) config('f5ai.max_tokens', 0);

        if ($maxTokens > 0) {
            $payload['max_tokens'] = $maxTokens;
        }

        try {
            $response = $this->httpClient($timeout, [
                'X-Auth-Token' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'text/event-stream',
            ], true, $options['on_wait'] ?? null)
                ->post(rtrim((string) config('f5ai.api_url'), '/').'/chat/completions', $payload);
        } catch (ConnectionException $exception) {
            throw new F5AiException(
                'Не удалось связаться с F5AI API. Проверьте интернет-соединение и попробуйте снова.',
                previous: $exception
            );
        }

        if (! $response->successful()) {
            $message = $response->json('message')
                ?? $response->json('error.message')
                ?? $response->json('error')
                ?? $response->body();

            if (is_array($message)) {
                $message = json_encode($message, JSON_UNESCAPED_UNICODE);
            }

            throw new F5AiException('Ошибка F5AI API: '.$message);
        }

        $body = $response->toPsrResponse()->getBody();
        $contentType = strtolower((string) $response->header('Content-Type'));

        // Фолбэк: провайдер не поддержал stream и вернул обычный JSON.
        if (! str_contains($contentType, 'text/event-stream')) {
            $data = json_decode((string) $body, true);
            $content = $this->extractContent(is_array($data) ? $data : null);
            $onDelta($content);

            return $content;
        }

        $full = '';
        $buffer = '';
        $done = false;

        while (! $done && ! $body->eof()) {
            $chunk = $body->read(8192);

            if ($chunk === '' || $chunk === false) {
                usleep(20_000);
                continue;
            }

            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $pos), "\r");
                $buffer = substr($buffer, $pos + 1);

                $delta = $this->parseStreamLine($line, $done);

                if ($delta !== null && $delta !== '') {
                    $full .= $delta;
                    $onDelta($delta);
                }

                if ($done) {
                    break;
                }
            }
        }

        // Хвост буфера без перевода строки.
        if (! $done && trim($buffer) !== '') {
            $delta = $this->parseStreamLine(rtrim($buffer, "\r"), $done);

            if ($delta !== null && $delta !== '') {
                $full .= $delta;
                $onDelta($delta);
            }
        }

        if ($full === '') {
            throw new F5AiException('F5AI API вернул пустой поток ответа.');
        }

        return $full;
    }

    /**
     * Разбор одной SSE-строки формата OpenAI (data: {...} / data: [DONE]).
     */
    private function parseStreamLine(string $line, bool &$done): ?string
    {
        if ($line === '' || ! str_starts_with($line, 'data:')) {
            return null;
        }

        $data = trim(substr($line, 5));

        if ($data === '[DONE]') {
            $done = true;

            return null;
        }

        $json = json_decode($data, true);

        if (! is_array($json)) {
            return null;
        }

        $delta = $json['choices'][0]['delta']['content'] ?? null;

        if (is_string($delta)) {
            return $delta;
        }

        $text = $json['choices'][0]['text'] ?? null;

        return is_string($text) ? $text : null;
    }

    /**
     * @param  array<int, array{role: string, content: string|list<array<string, mixed>>}>  $messages
     * @param  array{
     *     model?: string|null,
     *     large?: bool,
     *     user?: User|null,
     *     temperature?: float|int,
     *     max_tokens?: int,
     *     timeout?: int
     * }  $options
     */
    public function chatCompletion(array $messages, array $options = []): string
    {
        $apiKey = config('f5ai.api_key');

        if (empty($apiKey)) {
            throw new F5AiException('F5AI API ключ не настроен. Добавьте F5AI_API_KEY в файл .env.');
        }

        $timeout = (int) ($options['timeout'] ?? config('f5ai.timeout', 300));
        $user = $options['user'] ?? null;
        $large = (bool) ($options['large'] ?? false);

        $model = $options['model'] ?? null;
        if (! is_string($model) || $model === '') {
            $model = F5AiModels::resolve($user instanceof User ? $user : null, $large);
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.4,
        ];

        $maxTokens = array_key_exists('max_tokens', $options)
            ? (int) $options['max_tokens']
            : (int) config('f5ai.max_tokens', 0);

        // 0 = без жёсткого лимита длины ответа (решает модель/провайдер).
        if ($maxTokens > 0) {
            $payload['max_tokens'] = $maxTokens;
        }

        try {
            $response = $this->httpClient($timeout, [
                'X-Auth-Token' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post(rtrim((string) config('f5ai.api_url'), '/').'/chat/completions', $payload);
        } catch (ConnectionException $exception) {
            throw new F5AiException(
                'Не удалось связаться с F5AI API. Проверьте интернет-соединение и попробуйте снова.',
                previous: $exception
            );
        }

        if (! $response->successful()) {
            $message = $response->json('message')
                ?? $response->json('error.message')
                ?? $response->json('error')
                ?? $response->body();

            if (is_array($message)) {
                $message = json_encode($message, JSON_UNESCAPED_UNICODE);
            }

            throw new F5AiException('Ошибка F5AI API: '.$message);
        }

        return $this->extractContent($response->json());
    }

    /**
     * @param  array<string, string>  $headers
     * @param  callable(): void|null  $onWait
     */
    private function httpClient(int $timeout, array $headers, bool $stream = false, ?callable $onWait = null): PendingRequest
    {
        $pending = Http::withHeaders($headers)->connectTimeout(15);

        if ($timeout <= 0) {
            LongRunningRequest::disableTimeouts();
            $pending = $pending
                ->timeout(0)
                ->withOptions([
                    'timeout' => 0,
                    'read_timeout' => 0,
                ]);
        } else {
            $pending = $pending->timeout($timeout);
        }

        if ($stream) {
            $options = ['stream' => true];

            if ($onWait !== null) {
                $lastPing = microtime(true);
                $options['progress'] = static function () use ($onWait, &$lastPing): void {
                    if ((microtime(true) - $lastPing) < 10) {
                        return;
                    }

                    $lastPing = microtime(true);
                    $onWait();
                };
            }

            $pending = $pending->withOptions($options);
        }

        return $pending;
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function extractContent(?array $data): string
    {
        if ($data === null) {
            throw new F5AiException('F5AI API вернул пустой ответ.');
        }

        if (isset($data['choices'][0]['message']['content'])) {
            return (string) $data['choices'][0]['message']['content'];
        }

        if (isset($data['message']['content'])) {
            return (string) $data['message']['content'];
        }

        throw new F5AiException('Не удалось извлечь текст ответа из ответа F5AI API.');
    }
}
